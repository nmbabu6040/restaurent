<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\GalleryImage;
use App\Models\HomeSection;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Promotion;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function __invoke()
    {
        $available = fn() => MenuItem::query()
            ->with(['category', 'activeVariations', 'activeAddons'])
            ->whereHas('category', fn($c) => $c->where('is_active', true))
            ->where('is_available', true)
            ->orderBy('sort_order');

        return view('home', [
            'sections' => HomeSection::ordered()->get(),
            'categories' => MenuCategory::active()->ordered()
                ->withCount(['items as items_count' => fn($q) => $q->where('is_available', true)])->get(),
            'features' => Feature::active()->ordered()->where('group', 'usp')->get(),
            'featured' => $available()->where('is_featured', true)->limit(6)->get(),
            'chefPicks' => $available()->where('is_special', true)->limit(3)->get(),
            'promotions' => Promotion::current()->ordered()->limit(6)->get(),
            'gallery' => GalleryImage::active()->ordered()->limit(8)->get(),
            'testimonials' => Testimonial::active()->ordered()->limit(6)->get(),
        ]);
    }
}
