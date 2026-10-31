<?php

namespace App\Http\Controllers;

use App\Models\Page;

class CmsPageController extends Controller
{
    public function show(string $slug)
    {
        return view('pages.custom', ['page' => Page::active()->where('slug', $slug)->firstOrFail()]);
    }

    public function privacy()
    {
        return $this->legal('privacy-policy', 'pages.privacy');
    }

    public function terms()
    {
        return $this->legal('terms-and-conditions', 'pages.terms');
    }

    /** The editable page when it exists; the original built-in text otherwise. */
    private function legal(string $slug, string $fallback)
    {
        $page = Page::active()->where('slug', $slug)->first();

        return $page ? view('pages.custom', compact('page')) : view($fallback);
    }
}
