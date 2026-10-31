<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            RestaurantSettingSeeder::class,
            MenuSeeder::class,
            ContentSeeder::class,
            TeamSeeder::class,
            PageSeeder::class
        ]);
    }
}
