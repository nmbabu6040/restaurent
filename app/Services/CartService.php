<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\MenuItem;
use App\Models\DeliveryZone;
use Illuminate\Validation\ValidationException;

class CartService
{
    private const KEY = 'cart';
    private const COUPON = 'cart_coupon';

    public function __construct(private CouponService $coupons) {}

    public static function cents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Line key: "{item}_{variation or 0}_{addon ids joined by dots, sorted}". */
    public static function lineKey(int $item, ?int $variation, array $addons): string
    {
        return $item . '_' . ($variation ?? 0) . '_' . implode('.', $addons);
    }

    /** @return array<string, array{item:int, variation:?int, addons:int[], qty:int}> */
    public function raw(): array
    {
        $stored = session(self::KEY, []);
        $cart = [];

        foreach (is_array($stored) ? $stored : [] as $key => $entry) {
            if (! is_array($entry)) { // legacy format: item id => quantity
                $entry = ['item' => (int) $key, 'variation' => null, 'addons' => [], 'qty' => (int) $entry];
            }

            $item = (int) ($entry['item'] ?? 0);
            $qty = (int) ($entry['qty'] ?? 0);

            if ($item < 1 || $qty < 1) {
                continue;
            }

            $variation = ! empty($entry['variation']) ? (int) $entry['variation'] : null;
            $addons = collect($entry['addons'] ?? [])->map(fn($id) => (int) $id)->filter()->unique()->sort()->values()->all();

            $cart[self::lineKey($item, $variation, $addons)] = [
                'item' => $item,
                'variation' => $variation,
                'addons' => $addons,
                'qty' => $qty,
            ];
        }

        return $cart;
    }

    public function count(): int
    {
        return array_sum(array_column($this->raw(), 'qty'));
    }

    /**
     * Check a customer's choices against the database.
     *
     * @return array{0:?int, 1:int[]} validated variation id and sorted add-on ids
     */
    public function resolveSelection(MenuItem $item, ?int $variationId, array $addonIds): array
    {
        $variation = null;

        if ($item->activeVariations->isNotEmpty()) {
            $variation = $item->activeVariations->firstWhere('id', $variationId);

            if (! $variation) {
                throw ValidationException::withMessages(['variation_id' => __('orders.errors.choose_variation')]);
            }
        }

        $ids = collect($addonIds)->map(fn($id) => (int) $id)->unique()->sort()->values();

        if ($ids->diff($item->activeAddons->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['addons' => __('orders.errors.bad_addon')]);
        }

        return [$variation?->id, $ids->all()];
    }

    public function add(int $item, ?int $variation, array $addons, int $qty): void
    {
        $cart = $this->raw();
        $key = self::lineKey($item, $variation, $addons);

        if (! isset($cart[$key]) && count($cart) >= config('restaurant.ordering.max_lines')) {
            throw ValidationException::withMessages(['item_id' => __('orders.errors.too_many')]);
        }

        $cart[$key] = [
            'item' => $item,
            'variation' => $variation,
            'addons' => $addons,
            'qty' => min($this->maxQty(), ($cart[$key]['qty'] ?? 0) + $qty),
        ];

        session([self::KEY => $cart]);
    }

    public function set(string $key, int $qty): void
    {
        $cart = $this->raw();

        if (! isset($cart[$key])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = min($this->maxQty(), $qty);
        }

        session([self::KEY => $cart]);
    }

    public function remove(string $key): void
    {
        $this->set($key, 0);
    }

    public function clear(): void
    {
        session()->forget([self::KEY, self::COUPON]);
    }

    public function applyCoupon(string $code): void
    {
        $subtotal = (int) $this->summary()['subtotal'];

        if ($subtotal <= 0) {
            throw ValidationException::withMessages(['code' => __('orders.errors.empty')]);
        }

        $coupon = $this->coupons->find($code);
        [, $error] = $this->coupons->evaluate($coupon, $subtotal);

        if ($error) {
            throw ValidationException::withMessages(['code' => __('orders.coupon.' . $error)]);
        }

        session([self::COUPON => $coupon->code]);
    }

    public function removeCoupon(): void
    {
        session()->forget(self::COUPON);
    }

    public function minOrderCents(): int
    {
        return self::cents(config('restaurant.ordering.min_order'));
    }

    /**
     * Rebuild the cart from the database. Prices and discounts always come from here, never from the browser.
     * Lines whose item, size or extras are no longer orderable are dropped and reported in "removed".
     */
    public function summary(?OrderType $type = null, ?DeliveryZone $zone = null): array
    {
        $cart = $this->raw();
        $items = $cart
            ? MenuItem::withTrashed()->with(['category', 'variations', 'addons'])
            ->whereIn('id', array_unique(array_column($cart, 'item')))->get()->keyBy('id')
            : collect();

        $lines = collect();
        $removed = [];
        $clean = [];

        foreach ($cart as $key => $entry) {
            $item = $items->get($entry['item']);

            if (! $item) {
                continue;
            }

            if ($item->trashed() || ! $item->is_available || ! $item->category) {
                $removed[] = $item->name;

                continue;
            }

            $variations = $item->variations->where('is_active', true);
            $variation = null;

            if ($variations->isNotEmpty()) {
                $variation = $variations->firstWhere('id', $entry['variation']);

                if (! $variation) {
                    $removed[] = $item->name;

                    continue;
                }
            }

            $addons = $item->addons->where('is_active', true)->whereIn('id', $entry['addons'])->values();

            if ($addons->count() !== count($entry['addons'])) {
                $removed[] = $item->name;

                continue;
            }

            $unit = self::cents($variation ? $variation->price : $item->price)
                + (int) $addons->sum(fn($a) => self::cents($a->price));

            $lines->push([
                'key' => $key,
                'item' => $item,
                'variation' => $variation,
                'addons' => $addons,
                'qty' => $entry['qty'],
                'unit' => $unit,
                'total' => $unit * $entry['qty'],
            ]);
            $clean[$key] = $entry;
        }

        if ($clean !== $cart) {
            session([self::KEY => $clean]);
        }

        $subtotal = (int) $lines->sum('total');
        $coupon = null;
        $couponError = null;
        $discount = 0;
        $code = session(self::COUPON);

        if ($code) {
            if ($subtotal <= 0) {
                session()->forget(self::COUPON);
            } else {
                [$discount, $couponError] = $this->coupons->evaluate($this->coupons->find($code), $subtotal);

                if ($couponError) {
                    session()->forget(self::COUPON);
                    $discount = 0;
                } else {
                    $coupon = ['code' => $code, 'discount' => $discount];
                }
            }
        }

        return [
            'lines' => $lines,
            'removed' => $removed,
            'count' => $lines->sum('qty'),
            'min_order' => $this->minOrderCents(),
            'coupon' => $coupon,
            'coupon_error' => $couponError,
        ] + $this->totals($subtotal, $type, $discount, $zone);
    }


    /** @return array{subtotal:int, delivery_fee:int, discount:int, total:int} all in paisa */
    public function totals(int $subtotal, ?OrderType $type, int $discount = 0, ?DeliveryZone $zone = null): array
    {
        $fee = 0;

        if ($type === OrderType::Delivery) {
            // A delivery area has its own fee. Its free-delivery limit overrides the general one (0 = never free there).
            $fee = self::cents($zone ? $zone->fee : config('restaurant.ordering.delivery_fee'));
            $freeOver = (float) ($zone && $zone->free_over !== null ? $zone->free_over : config('restaurant.ordering.free_delivery_over'));

            // Free delivery is judged on the subtotal before any coupon.
            if ($freeOver > 0 && $subtotal >= self::cents($freeOver)) {
                $fee = 0;
            }
        }

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $fee,
            'discount' => $discount,
            'total' => $subtotal - $discount + $fee,
        ];
    }

    private function maxQty(): int
    {
        return (int) config('restaurant.ordering.max_qty');
    }

    /** How many portions of a dish (all sizes together) are already in the cart. */
    public function quantityOf(int $itemId): int
    {
        return (int) collect($this->raw())->where('item', $itemId)->sum('qty');
    }
}
