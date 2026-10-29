<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ReservationStatus;
use PHPUnit\Framework\TestCase;

class StatusTransitionsTest extends TestCase
{
    public function test_delivery_orders_go_out_for_delivery_and_pickup_orders_become_ready(): void
    {
        $this->assertContains(OrderStatus::OutForDelivery, OrderStatus::Preparing->transitions(OrderType::Delivery));
        $this->assertNotContains(OrderStatus::Ready, OrderStatus::Preparing->transitions(OrderType::Delivery));

        $this->assertContains(OrderStatus::Ready, OrderStatus::Preparing->transitions(OrderType::Pickup));
        $this->assertNotContains(OrderStatus::OutForDelivery, OrderStatus::Preparing->transitions(OrderType::Pickup));
    }

    public function test_final_order_states_have_no_transitions(): void
    {
        foreach ([OrderStatus::Completed, OrderStatus::Cancelled] as $status) {
            $this->assertTrue($status->isFinal());
            $this->assertSame([], $status->transitions(OrderType::Delivery));
        }
    }

    public function test_orders_cannot_skip_steps(): void
    {
        $this->assertNotContains(OrderStatus::Completed, OrderStatus::Pending->transitions(OrderType::Delivery));
        $this->assertNotContains(OrderStatus::Preparing, OrderStatus::Pending->transitions(OrderType::Delivery));
    }

    public function test_reservation_transitions(): void
    {
        $this->assertSame(
            [ReservationStatus::Confirmed, ReservationStatus::Rejected, ReservationStatus::Cancelled],
            ReservationStatus::Pending->transitions(),
        );
        $this->assertSame([ReservationStatus::Cancelled], ReservationStatus::Confirmed->transitions());
        $this->assertSame([], ReservationStatus::Cancelled->transitions());
        $this->assertSame([], ReservationStatus::Rejected->transitions());
    }

    public function test_only_pending_and_confirmed_reservations_hold_seats(): void
    {
        $this->assertTrue(ReservationStatus::Pending->blocksCapacity());
        $this->assertTrue(ReservationStatus::Confirmed->blocksCapacity());
        $this->assertFalse(ReservationStatus::Cancelled->blocksCapacity());
        $this->assertFalse(ReservationStatus::Rejected->blocksCapacity());
    }
}
