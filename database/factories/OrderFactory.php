<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'MO-' . Str::upper(Str::random(8)),
            'token' => Str::random(40),
            'type' => 'delivery',
            'name' => fake()->name(),
            'phone' => '01712345678',
            'email' => 'guest@example.com',
            'address' => 'House 1, Road 2',
            'notes' => null,
            'subtotal' => '380.00',
            'delivery_fee' => '60.00',
            'discount' => '0.00',
            'total' => '440.00',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'status_changed_at' => now(),
            'locale' => 'en',
        ];
    }

    public function pickup(): static
    {
        return $this->state(['type' => 'pickup', 'address' => null, 'delivery_fee' => '0.00', 'total' => '380.00']);
    }

    public function online(): static
    {
        return $this->state(['payment_method' => 'sslcommerz']);
    }

    public function paid(): static
    {
        return $this->state(['payment_status' => 'paid']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
