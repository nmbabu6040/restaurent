<?php

namespace Database\Seeders;

use App\Models\RestaurantSetting;
use Illuminate\Database\Seeder;

class RestaurantSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'name' => ['Meher Cuisine', 'মেহের কুইজিন'],
            'cuisine' => ['Bangladeshi & more', 'বাংলাদেশি ও আরও অনেক কিছু'],
            'tagline' => ['Good food, warm company.', 'ভালো খাবার, আন্তরিক সঙ্গ।'],
            'hero_title' => [
                'A table set with care, a meal made to remember',
                'যত্নে সাজানো টেবিল, মনে রাখার মতো খাবার',
            ],
            'hero_subtitle' => [
                'Fresh ingredients, honest recipes and warm hospitality, served every day.',
                'তাজা উপকরণ, খাঁটি রেসিপি আর আন্তরিক আতিথেয়তা, প্রতিদিন।',
            ],
            'phone' => ['+880 1XXX-XXXXXX', null],
            'email' => ['hello@meher.test', null],
            'address' => ['Your address here', 'আপনার ঠিকানা এখানে'],
            'hours' => [
                "Sat–Thu: 11:00 AM – 11:00 PM\nFri: 1:00 PM – 11:00 PM",
                "শনি–বৃহস্পতি: সকাল ১১টা – রাত ১১টা\nশুক্র: দুপুর ১টা – রাত ১১টা",
            ],
            'facebook' => ['#', null],
            'instagram' => ['#', null],
        ];

        foreach ($settings as $key => [$en, $bn]) {
            RestaurantSetting::firstOrCreate(
                ['key' => $key],
                ['value_en' => $en, 'value_bn' => $bn],
            );
        }
    }
}
