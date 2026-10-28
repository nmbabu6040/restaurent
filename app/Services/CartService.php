<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\MenuItem;
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

    /** @return array<int,int> item id => quantity */
    public function raw(): array
    {
        $stored = session(self::KEY, []);
        $cart = [];

        foreach (is_array($stored) ? $stored : [] as $id => $qty) {
            if ((int) $id > 0 && (int) $qty > 0) {
                $cart[(int) $id] = (int) $qty;
            }
        }

        return $cart;
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function add(int $id, int $qty): void
    {
        $cart = $this->raw();

        if (! isset($cart[$id]) && count($cart) >= config('restaurant.ordering.max_lines')) {
            throw ValidationException::withMessages(['item_id' => __('orders.errors.too_many')]);
        }

        $cart[$id] = min($this->maxQty(), ($cart[$id] ?? 0) + $qty);
        session([self::KEY => $cart]);
    }

    public function set(int $id, int $qty): void
    {
        $cart = $this->raw();

        if (! isset($cart[$id])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = min($this->maxQty(), $qty);
        }

        session([self::KEY => $cart]);
    }

    public function remove(int $id): void
    {
        $this->set($id, 0);
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
     * Items that are no longer orderable are dropped from the session and reported in "removed".
     */
    public function summary(?OrderType $type = null): array
    {
        $cart = $this->raw();
        $items = $cart
            ? MenuItem::withTrashed()->with('category')->whereIn('id', array_keys($cart))->get()->keyBy('id')
            : collect();

        $lines = collect();
        $removed = [];
        $clean = [];

        foreach ($cart as $id => $qty) {
            $item = $items->get($id);

            if (! $item) {
                continue;
            }

            if ($item->trashed() || ! $item->is_available || ! $item->category) {
                $removed[] = $item->name;

                continue;
            }

            $unit = self::cents($item->price);
            $lines->push(['item' => $item, 'qty' => $qty, 'unit' => $unit, 'total' => $unit * $qty]);
            $clean[$id] = $qty;
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
        ] + $this->totals($subtotal, $type, $discount);
    }

    /** @return array{subtotal:int, delivery_fee:int, discount:int, total:int} all in paisa */
    public function totals(int $subtotal, ?OrderType $type, int $discount = 0): array
    {
        $fee = 0;

        if ($type === OrderType::Delivery) {
            $fee = self::cents(config('restaurant.ordering.delivery_fee'));
            $freeOver = (float) config('restaurant.ordering.free_delivery_over');

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
}
