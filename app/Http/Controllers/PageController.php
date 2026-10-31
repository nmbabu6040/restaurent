<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\Promotion;
use App\Models\TeamMember;
use App\Models\Feature;
use App\Models\Testimonial;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about', [
            'team' => TeamMember::active()->ordered()->get(),
            'gallery' => GalleryImage::active()->ordered()->limit(4)->get(),
            'values' => Feature::active()->ordered()->where('group', 'value')->get(),
        ]);
    }

    public function gallery()
    {
        return view('pages.gallery', [
            'images' => GalleryImage::active()->ordered()->paginate(12),
        ]);
    }

    public function offers()
    {
        return view('pages.offers', [
            'promotions' => Promotion::current()->ordered()->get(),
        ]);
    }

    public function testimonials()
    {
        return view('pages.testimonials', [
            'testimonials' => Testimonial::active()->ordered()->get(),
        ]);
    }

    public function faq()
    {
        return view('pages.faq', [
            'faqs' => Faq::active()->ordered()->get(),
        ]);
    }
}
