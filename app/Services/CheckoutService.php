<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private CartService $cart, private CouponService $coupons, private InventoryService $inventory) {}

    /** @return array<int, OrderType> */
    public function availableTypes(): array
    {
        return array_values(array_filter([
            config('restaurant.ordering.delivery_enabled') ? OrderType::Delivery : null,
            config('restaurant.ordering.pickup_enabled') ? OrderType::Pickup : null,
        ]));
    }

    /** The chosen delivery area. It is required when the restaurant has set up any active areas. */
    public function zoneFor(OrderType $type, mixed $id): ?DeliveryZone
    {
        if ($type !== OrderType::Delivery || ! DeliveryZone::active()->exists()) {
            return null;
        }

        $zone = filled($id) ? DeliveryZone::active()->find($id) : null;

        if (! $zone) {
            throw ValidationException::withMessages(['delivery_zone_id' => __('orders.errors.zone_required')]);
        }

        return $zone;
    }

    public function place(array $data, string $locale): Order
    {
        $type = OrderType::from($data['type']);
        $zone = $this->zoneFor($type, $data['delivery_zone_id'] ?? null);

        $order = DB::transaction(function () use ($data, $type, $locale, $zone) {
            $summary = $this->cart->summary($type, $zone);

            if ($summary['removed']) {
                throw ValidationException::withMessages([
                    'cart' => __('orders.errors.removed', ['items' => implode(', ', $summary['removed'])]),
                ]);
            }

            if ($summary['lines']->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('orders.errors.empty')]);
            }

            // A delivery area can ask for its own minimum order.
            $minimum = ($zone && $zone->min_order !== null) ? CartService::cents($zone->min_order) : $summary['min_order'];

            if ($summary['subtotal'] < $minimum) {
                throw ValidationException::withMessages([
                    'cart' => __('orders.errors.min_order', ['amount' => money_c($minimum)]),
                ]);
            }

            // Lock the coupon row so two simultaneous orders cannot both use the last redemption.
            $discount = 0;
            $couponCode = null;

            if ($summary['coupon']) {
                $coupon = Coupon::where('code', $summary['coupon']['code'])->lockForUpdate()->first();
                [$discount, $error] = $this->coupons->evaluate($coupon, $summary['subtotal']);

                if ($error) {
                    throw ValidationException::withMessages(['cart' => __('orders.coupon.' . $error)]);
                }

                $coupon->increment('used_count');
                $couponCode = $coupon->code;
            }

            $total = $summary['subtotal'] - $discount + $summary['delivery_fee'];

            do {
                $reference = 'MO-' . Str::upper(Str::random(8));
            } while (Order::where('reference', $reference)->exists());

            $dec = fn(int $cents) => number_format($cents / 100, 2, '.', '');

            $order = Order::create([
                'reference' => $reference,
                'token' => Str::random(40),
                'type' => $type,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $type === OrderType::Delivery ? ($data['address'] ?? null) : null,
                'delivery_zone_id' => $zone?->id,
                'zone_en' => $zone?->name_en,
                'zone_bn' => $zone?->name_bn,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $dec($summary['subtotal']),
                'delivery_fee' => $dec($summary['delivery_fee']),
                'discount' => $dec($discount),
                'coupon_code' => $couponCode,
                'total' => $dec($total),
                'payment_method' => $data['payment_method'],
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'status_changed_at' => now(),
                'locale' => $locale,
            ]);

            // Takes stock and returns each line's cost snapshot. Throws (and rolls everything back) on a shortage.
            $plan = $this->inventory->apply($order, $summary['lines']);

            foreach ($summary['lines'] as $line) {
                $order->items()->create([
                    'menu_item_id' => $line['item']->id,
                    'name_en' => $line['item']->name_en,
                    'name_bn' => $line['item']->name_bn,
                    'variation_en' => $line['variation']?->name_en,
                    'variation_bn' => $line['variation']?->name_bn,
                    'addons' => $line['addons']->isEmpty()
                        ? null
                        : $line['addons']->map(fn($a) => [
                            'name_en' => $a->name_en,
                            'name_bn' => $a->name_bn,
                            'price' => (float) $a->price,
                        ])->values()->all(),
                    'unit_price' => $dec($line['unit']),
                    'unit_cost' => $plan[$line['key']]['unit_cost'],
                    'consumed' => $plan[$line['key']]['consumed'],
                    'quantity' => $line['qty'],
                    'line_total' => $dec($line['total']),
                ]);
            }

            return $order;
        });

        $this->cart->clear();

        return $order->load('items');
    }
}
