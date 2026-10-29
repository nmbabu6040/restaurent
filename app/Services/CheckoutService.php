<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private CartService $cart, private CouponService $coupons) {}

    /** @return array<int, OrderType> */
    public function availableTypes(): array
    {
        return array_values(array_filter([
            config('restaurant.ordering.delivery_enabled') ? OrderType::Delivery : null,
            config('restaurant.ordering.pickup_enabled') ? OrderType::Pickup : null,
        ]));
    }

    public function place(array $data, string $locale): Order
    {
        $type = OrderType::from($data['type']);

        $order = DB::transaction(function () use ($data, $type, $locale) {
            $summary = $this->cart->summary($type);

            if ($summary['removed']) {
                throw ValidationException::withMessages([
                    'cart' => __('orders.errors.removed', ['items' => implode(', ', $summary['removed'])]),
                ]);
            }

            if ($summary['lines']->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('orders.errors.empty')]);
            }

            if ($summary['subtotal'] < $summary['min_order']) {
                throw ValidationException::withMessages([
                    'cart' => __('orders.errors.min_order', ['amount' => money_c($summary['min_order'])]),
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
