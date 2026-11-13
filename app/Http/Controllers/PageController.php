<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Feature;
use App\Models\GalleryImage;
use App\Models\Promotion;
use App\Models\SpacePhoto;
use App\Models\TeamMember;
use App\Models\Testimonial;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about', [
            'team' => TeamMember::active()->ordered()->get(),
            'values' => Feature::active()->ordered()->where('group', 'value')->get(),
            'space' => SpacePhoto::active()->ordered()->limit(12)->get(),
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
