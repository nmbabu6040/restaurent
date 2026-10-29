<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Services\CouponService;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    private function evaluate(array $attributes, int $subtotal = 38000): array
    {
        return (new CouponService)->evaluate(Coupon::factory()->make($attributes), $subtotal);
    }

    public function test_percent_discount(): void
    {
        $this->assertSame([3800, null], $this->evaluate(['type' => 'percent', 'value' => '10']));
    }

    public function test_percent_discount_respects_the_cap(): void
    {
        $this->assertSame([10000, null], $this->evaluate(['type' => 'percent', 'value' => '50', 'max_discount' => '100']));
    }

    public function test_fixed_discount_never_exceeds_the_subtotal(): void
    {
        $this->assertSame([38000, null], $this->evaluate(['type' => 'fixed', 'value' => '1000']));
        $this->assertSame([5000, null], $this->evaluate(['type' => 'fixed', 'value' => '50']));
    }

    public function test_rejections(): void
    {
        $this->assertSame([0, 'invalid'], (new CouponService)->evaluate(null, 38000));
        $this->assertSame([0, 'invalid'], $this->evaluate(['is_active' => false]));
        $this->assertSame([0, 'invalid'], $this->evaluate(['starts_at' => now()->addDay()]));
        $this->assertSame([0, 'expired'], $this->evaluate(['ends_at' => now()->subMinute()]));
        $this->assertSame([0, 'used_up'], $this->evaluate(['usage_limit' => 5, 'used_count' => 5]));
        $this->assertSame([0, 'min_subtotal'], $this->evaluate(['min_subtotal' => '500']));
    }
}
