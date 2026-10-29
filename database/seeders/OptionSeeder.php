<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $item = MenuItem::where('slug', 'kacchi-biryani')->first();

        if (! $item || $item->variations()->exists() || $item->addons()->exists()) {
            return;
        }

        foreach ([['Half', 'হাফ', 250], ['Full', 'ফুল', 380]] as $i => [$en, $bn, $price]) {
            $item->variations()->create(['name_en' => $en, 'name_bn' => $bn, 'price' => $price, 'sort_order' => $i]);
        }

        foreach ([['Extra borhani', 'এক্সট্রা বোরহানি', 60], ['Egg', 'ডিম', 30], ['Salad', 'সালাদ', 40]] as $i => [$en, $bn, $price]) {
            $item->addons()->create(['name_en' => $en, 'name_bn' => $bn, 'price' => $price, 'sort_order' => $i]);
        }
    }
}
