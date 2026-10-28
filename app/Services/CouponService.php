<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Str;

class CouponService
{
    public function find(string $code): ?Coupon
    {
        return Coupon::where('code', Str::upper(trim($code)))->first();
    }

    /**
     * @return array{0:int,1:?string} discount in paisa and an error key (invalid, expired, used_up, min_subtotal)
     */
    public function evaluate(?Coupon $coupon, int $subtotal): array
    {
        if (! $coupon || ! $coupon->is_active) {
            return [0, 'invalid'];
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return [0, 'invalid'];
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return [0, 'expired'];
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return [0, 'used_up'];
        }
        if ($subtotal < CartService::cents($coupon->min_subtotal)) {
            return [0, 'min_subtotal'];
        }

        $discount = $coupon->type === 'percent'
            ? (int) floor($subtotal * (float) $coupon->value / 100)
            : CartService::cents($coupon->value);

        if ($coupon->type === 'percent' && $coupon->max_discount !== null) {
            $discount = min($discount, CartService::cents($coupon->max_discount));
        }

        return [min($discount, $subtotal), null];
    }
}
