<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['privacy-policy', 'privacy', 10], ['terms-and-conditions', 'terms', 20]] as [$slug, $key, $order]) {
            if (Page::where('slug', $slug)->exists()) {
                continue;
            }

            $body = fn(string $lang) => collect(trans("pages.{$key}.sections", [], $lang))
                ->map(fn($section) => "## {$section['h']}\n\n{$section['p']}")->implode("\n\n");

            Page::create([
                'slug' => $slug,
                'title_en' => trans("pages.{$key}.title", [], 'en'),
                'title_bn' => trans("pages.{$key}.title", [], 'bn'),
                'body_en' => $body('en'),
                'body_bn' => $body('bn'),
                'is_system' => true,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }
}
