<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rider> */
class RiderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => '01712345678',
            'vehicle' => 'bike',
            'fee_per_delivery' => '50.00',
            'is_active' => true,
            'is_online' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function offline(): static
    {
        return $this->state(['is_online' => false]);
    }
}
