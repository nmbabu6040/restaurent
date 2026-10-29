<?php

namespace Database\Factories;

use App\Models\FoodVariation;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FoodVariation> */
class FoodVariationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name_en' => fake()->unique()->word(),
            'name_bn' => null,
            'price' => '250.00',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
