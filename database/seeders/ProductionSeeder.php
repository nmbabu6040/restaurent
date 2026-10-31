<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /** Only what a live site needs on day one: the admin account and the editable settings. */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            RestaurantSettingSeeder::class,
            PageSeeder::class
        ]);
    }
}
