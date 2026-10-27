<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Starters', 'স্টার্টার'],
            ['Rice & Biryani', 'ভাত ও বিরিয়ানি'],
            ['Curries', 'তরকারি ও ভুনা'],
            ['Grill & Kebab', 'গ্রিল ও কাবাব'],
            ['Desserts', 'মিষ্টান্ন'],
            ['Drinks', 'পানীয়'],
        ];

        $map = [];
        foreach ($categories as $i => [$en, $bn]) {
            $map[$en] = MenuCategory::updateOrCreate(
                ['slug' => Str::slug($en)],
                ['name_en' => $en, 'name_bn' => $bn, 'sort_order' => $i + 1, 'is_active' => true],
            );
        }

        $items = [
            [
                'Starters',
                'Crispy Samosa',
                'মচমচে সমুচা',
                'Four golden pastries stuffed with spiced potato and peas.',
                'মসলাদার আলু ও মটরশুঁটি ভরা চারটি সোনালি সমুচা।',
                80,
                [],
                ['vegetarian']
            ],
            [
                'Starters',
                'Beguni',
                'বেগুনি',
                'Sliced eggplant dipped in spiced gram-flour batter and fried crisp.',
                'মসলা মেশানো বেসনে ডুবিয়ে ভাজা মচমচে বেগুন।',
                70,
                [],
                ['vegetarian']
            ],
            [
                'Rice & Biryani',
                'Kacchi Biryani',
                'কাচ্চি বিরিয়ানি',
                'Slow-cooked mutton and fragrant basmati, steamed together in its own aroma.',
                'সুগন্ধি বাসমতি চাল আর খাসির মাংস দমে রান্না করা ঐতিহ্যবাহী কাচ্চি।',
                380,
                ['featured', 'special'],
                ['halal']
            ],
            [
                'Rice & Biryani',
                'Beef Tehari',
                'বিফ তেহারি',
                'Mustard-oil rice cooked with tender beef and green chilli.',
                'সরিষার তেলে রান্না করা গরুর মাংসের তেহারি, সঙ্গে কাঁচা মরিচ।',
                260,
                [],
                ['halal', 'spicy']
            ],
            [
                'Curries',
                'Beef Bhuna',
                'গরুর ভুনা',
                'Slow-roasted beef in a thick, deeply spiced masala.',
                'ঘন মশলায় অনেকক্ষণ কষিয়ে রান্না করা গরুর ভুনা।',
                320,
                ['featured'],
                ['halal', 'spicy']
            ],
            [
                'Curries',
                'Shorshe Ilish',
                'সরষে ইলিশ',
                'Hilsa steaks in a pungent mustard gravy, a Bengali classic.',
                'ঝাঁঝালো সরষে বাটা মাখানো ইলিশ, বাঙালির চিরায়ত পদ।',
                550,
                ['featured', 'special'],
                ['halal']
            ],
            [
                'Curries',
                'Dal Fry',
                'ডাল ফ্রাই',
                'Yellow lentils tempered with garlic, cumin and dried chilli.',
                'রসুন, জিরা ও শুকনো মরিচের ফোড়নে রান্না করা হলুদ ডাল।',
                140,
                [],
                ['vegetarian']
            ],
            [
                'Grill & Kebab',
                'Chicken Tikka',
                'চিকেন টিক্কা',
                'Yogurt-marinated chicken, charred over open flame.',
                'দই-মশলায় মাখানো মুরগি, কয়লার আঁচে ঝলসানো।',
                280,
                [],
                ['halal']
            ],
            [
                'Grill & Kebab',
                'Seekh Kebab',
                'সিক কাবাব',
                'Minced beef skewers with herbs and warm spices.',
                'হার্বস ও গরম মশলা মেশানো কিমার সিক কাবাব।',
                320,
                [],
                ['halal', 'spicy']
            ],
            [
                'Desserts',
                'Mishti Doi',
                'মিষ্টি দই',
                'Caramelised sweet yogurt set in a clay pot.',
                'মাটির পাত্রে জমানো ক্যারামেল স্বাদের মিষ্টি দই।',
                90,
                ['special'],
                ['vegetarian']
            ],
            [
                'Desserts',
                'Firni',
                'ফিরনি',
                'Creamy ground-rice pudding scented with cardamom.',
                'এলাচের সুবাসে চালের গুঁড়োর ক্রিমি ফিরনি।',
                110,
                [],
                ['vegetarian'],
                false
            ],
            [
                'Drinks',
                'Borhani',
                'বোরহানি',
                'Tangy spiced yogurt drink, the perfect partner to biryani.',
                'টক-ঝাল মশলাদার দইয়ের পানীয়, বিরিয়ানির সেরা সঙ্গী।',
                70,
                [],
                ['vegetarian']
            ],
            [
                'Drinks',
                'Mango Lassi',
                'ম্যাঙ্গো লাচ্ছি',
                'Chilled yogurt blended with ripe mango.',
                'পাকা আমের সঙ্গে ঠান্ডা দইয়ের লাচ্ছি।',
                120,
                [],
                ['vegetarian']
            ],
        ];

        foreach ($items as $i => $row) {
            [$cat, $nameEn, $nameBn, $descEn, $descBn, $price, $flags, $dietary] = $row;
            $available = $row[8] ?? true;

            MenuItem::updateOrCreate(
                ['slug' => Str::slug($nameEn)],
                [
                    'menu_category_id' => $map[$cat]->id,
                    'name_en' => $nameEn,
                    'name_bn' => $nameBn,
                    'description_en' => $descEn,
                    'description_bn' => $descBn,
                    'price' => $price,
                    'dietary' => $dietary,
                    'is_available' => $available,
                    'is_featured' => in_array('featured', $flags, true),
                    'is_special' => in_array('special', $flags, true),
                    'sort_order' => $i + 1,
                ],
            );
        }
    }
}
