<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => 'percent',
            'value' => '10.00',
            'min_subtotal' => '0.00',
            'max_discount' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'is_active' => true,
        ];
    }
}
