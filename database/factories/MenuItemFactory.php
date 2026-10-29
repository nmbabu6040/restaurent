<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MenuItem> */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'menu_category_id' => MenuCategory::factory(),
            'name_en' => Str::title($name),
            'name_bn' => null,
            'slug' => Str::slug($name),
            'description_en' => fake()->sentence(),
            'price' => '380.00',
            'is_available' => true,
            'is_featured' => false,
            'is_special' => false,
            'sort_order' => 0,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }
}
