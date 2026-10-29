<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Reservation> */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'MC-' . Str::upper(Str::random(8)),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '01712345678',
            'reserved_at' => now()->addDay()->setTime(19, 0),
            'duration_minutes' => 90,
            'party_size' => 2,
            'message' => null,
            'status' => 'pending',
            'status_changed_at' => now(),
            'locale' => 'en',
        ];
    }
}
