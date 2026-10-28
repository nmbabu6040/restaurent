<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        if (TeamMember::count() > 0) {
            return;
        }

        TeamMember::create([
            'name' => 'Sample Chef',
            'role_en' => 'Head Chef',
            'role_bn' => 'হেড শেফ',
            'bio_en' => 'Leads our kitchen with a focus on traditional recipes and fresh, seasonal ingredients.',
            'bio_bn' => 'ঐতিহ্যবাহী রেসিপি আর তাজা মৌসুমি উপকরণের ওপর জোর দিয়ে আমাদের রান্নাঘর পরিচালনা করেন।',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        TeamMember::create([
            'name' => 'Sample Manager',
            'role_en' => 'Restaurant Manager',
            'role_bn' => 'রেস্তোরাঁ ম্যানেজার',
            'bio_en' => 'Makes sure every guest feels welcome from the first hello to the last bite.',
            'bio_bn' => 'প্রথম অভিবাদন থেকে শেষ লোকমা পর্যন্ত প্রতিটি অতিথি যেন আপ্যায়িত বোধ করেন, তা নিশ্চিত করেন।',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }
}
