<?php

namespace Database\Factories;

use App\Models\FoodAddon;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FoodAddon> */
class FoodAddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name_en' => fake()->unique()->word(),
            'name_bn' => null,
            'price' => '30.00',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
