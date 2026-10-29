<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function robots()
    {
        // Never let a staging or local copy be indexed.
        if (! app()->isProduction()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /orders',
            'Disallow: /reservations/',
            'Disallow: /payments',
            'Disallow: /lang/',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap()
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $urls = collect([
                route('home'),
                route('menu.index'),
                route('about'),
                route('gallery'),
                route('offers'),
                route('testimonials'),
                route('faq'),
                route('contact.create'),
                route('reservations.create'),
                route('privacy'),
                route('terms'),
            ])->map(fn($loc) => ['loc' => $loc, 'lastmod' => null]);

            MenuItem::query()
                ->whereHas('category', fn($q) => $q->where('is_active', true))
                ->orderBy('id')
                ->get(['slug', 'updated_at'])
                ->each(fn($item) => $urls->push([
                    'loc' => route('menu.show', $item->slug),
                    'lastmod' => $item->updated_at?->toAtomString(),
                ]));

            $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($urls as $u) {
                $out .= '  <url><loc>' . e($u['loc']) . '</loc>' . ($u['lastmod'] ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '') . '</url>' . "\n";
            }

            return $out . '</urlset>' . "\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
