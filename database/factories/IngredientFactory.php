<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ingredient> */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'unit' => 'kg',
            'stock' => '10.000',
            'reorder_level' => '2.000',
            'unit_cost' => '100.0000',
            'is_active' => true,
        ];
    }
}
