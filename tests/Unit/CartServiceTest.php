<?php

namespace Tests\Unit;

use App\Enums\OrderType;
use App\Services\CartService;
use App\Services\CouponService;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    public function test_cents_rounds_correctly(): void
    {
        $this->assertSame(38000, CartService::cents('380.00'));
        $this->assertSame(29, CartService::cents(0.29));
        $this->assertSame(1999, CartService::cents('19.99'));
    }

    public function test_line_keys_are_stable(): void
    {
        $this->assertSame('5_0_', CartService::lineKey(5, null, []));
        $this->assertSame('5_2_7.9', CartService::lineKey(5, 2, [7, 9]));
    }

    public function test_delivery_fee_and_free_delivery_threshold(): void
    {
        $cart = new CartService(new CouponService);

        $this->assertSame(6000, $cart->totals(149999, OrderType::Delivery)['delivery_fee']);
        $this->assertSame(0, $cart->totals(150000, OrderType::Delivery)['delivery_fee']);
        $this->assertSame(0, $cart->totals(10000, OrderType::Pickup)['delivery_fee']);
    }

    public function test_discount_is_subtracted_and_free_delivery_uses_the_subtotal_before_it(): void
    {
        $cart = new CartService(new CouponService);

        $totals = $cart->totals(150000, OrderType::Delivery, 20000);

        $this->assertSame(0, $totals['delivery_fee']);
        $this->assertSame(130000, $totals['total']);
    }
}
