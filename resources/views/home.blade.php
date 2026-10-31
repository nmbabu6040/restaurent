@php $brand = setting('name', config('app.name')); @endphp

<x-layouts.app :description="setting('hero_subtitle')" :image="$gallery->first()?->image_url">
    @push('head')
        @php
            $sameAs = array_values(
                array_filter([setting('facebook'), setting('instagram')], fn($u) => $u && str_starts_with($u, 'http')),
            );
            $ld = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Restaurant',
                'name' => $brand,
                'description' => setting('hero_subtitle'),
                'url' => url('/'),
                'image' => $gallery->first()?->image_url,
                'servesCuisine' => setting('cuisine'),
                'telephone' => setting('phone'),
                'address' => setting('address'),
                'sameAs' => $sameAs ?: null,
                'hasMenu' => ['@type' => 'Menu', 'url' => route('menu.index')],
                'acceptsReservations' => route('reservations.create'),
            ]);
        @endphp
        <x-seo.json-ld :data="$ld" />
    @endpush

    @foreach ($sections as $section)
        @continue(!$section->is_visible)
        @includeIf($section->is_custom ? 'home.sections.custom' : 'home.sections.' . $section->key, [
            'section' => $section,
        ])
    @endforeach
</x-layouts.app>
