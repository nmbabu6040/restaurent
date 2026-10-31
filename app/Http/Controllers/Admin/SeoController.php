<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\RestaurantSetting;
use App\Services\ImageService;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public const PAGES = [
        'home' => 'Home page',
        'menu' => 'Menu',
        'about' => 'About',
        'gallery' => 'Gallery',
        'offers' => 'Offers',
        'testimonials' => 'Reviews page',
        'faq' => 'FAQ',
        'contact' => 'Contact',
        'reservations' => 'Reservations',
    ];

    public function edit()
    {
        $blank = fn($column) => fn($q) => $q->whereNull($column)->orWhere($column, '');

        return view('admin.seo.edit', [
            'values' => RestaurantSetting::cached(),
            'pages' => self::PAGES,
            'health' => [
                'Dishes without a description' => MenuItem::where($blank('description_en'))->count(),
                'Dishes without a photo' => MenuItem::where($blank('image_path'))->count(),
                'Pages without a search description' => Page::active()->where($blank('meta_description_en'))->count(),
            ],
        ]);
    }

    public function update(Request $request, ImageService $images)
    {
        $rules = [
            'seo_default_description_en' => ['nullable', 'string', 'max:300'],
            'seo_default_description_bn' => ['nullable', 'string', 'max:300'],
            'seo_google_verification' => ['nullable', 'regex:/^[A-Za-z0-9_\-]{10,120}$/'],
            'seo_bing_verification' => ['nullable', 'regex:/^[A-Za-z0-9_\-]{10,120}$/'],
            'seo_noindex_all' => ['required', 'boolean'],
            'seo_default_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=5000,max_height=5000'],
            'remove_seo_default_image' => ['nullable', 'boolean'],
        ];

        foreach (array_keys(self::PAGES) as $key) {
            foreach (['en', 'bn'] as $lang) {
                $rules["seo_{$key}_title_{$lang}"] = ['nullable', 'string', 'max:160'];
                $rules["seo_{$key}_description_{$lang}"] = ['nullable', 'string', 'max:300'];
            }
        }

        $data = $request->validate($rules);
        $put = fn(string $key, ?string $en, ?string $bn = null) => RestaurantSetting::updateOrCreate(['key' => $key], ['value_en' => $en, 'value_bn' => $bn]);

        $put('seo_default_description', $data['seo_default_description_en'] ?? null, $data['seo_default_description_bn'] ?? null);
        $put('seo_google_verification', $data['seo_google_verification'] ?? null);
        $put('seo_bing_verification', $data['seo_bing_verification'] ?? null);
        $put('seo_noindex_all', $request->boolean('seo_noindex_all') ? '1' : '0');

        foreach (array_keys(self::PAGES) as $key) {
            $put("seo_{$key}_title", $data["seo_{$key}_title_en"] ?? null, $data["seo_{$key}_title_bn"] ?? null);
            $put("seo_{$key}_description", $data["seo_{$key}_description_en"] ?? null, $data["seo_{$key}_description_bn"] ?? null);
        }

        $current = RestaurantSetting::cached()['seo_default_image']['en'] ?? null;

        if ($request->hasFile('seo_default_image')) {
            $images->delete($current);
            $put('seo_default_image', $images->storeSocial($request->file('seo_default_image'), 'seo'));
        } elseif ($request->boolean('remove_seo_default_image')) {
            $images->delete($current);
            $put('seo_default_image', null);
        }

        ActivityLog::record('seo.updated', 'Updated SEO settings');

        return redirect()->route('admin.seo.edit')->with('status', 'SEO settings saved.');
    }
}
