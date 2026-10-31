@php
    $image = setting_image('hero_image');
    $overlay = \App\Support\Color::isHex(setting('hero_overlay_color')) ? setting('hero_overlay_color') : '#172B4D';
    $opacity = min(90, max(0, (int) setting('hero_overlay_opacity', '55'))) / 100;
    $lightText = setting('hero_text_tone', 'light') !== 'dark';
    $padding =
        ['compact' => 'py-14 sm:py-20', 'normal' => 'py-20 sm:py-28', 'tall' => 'py-28 sm:py-44'][
            setting('hero_height', 'normal')
        ] ?? 'py-20 sm:py-28';
@endphp

{{-- With a photo, the section takes the overlay color so buttons and text stay readable over it. --}}
<x-site.section name="hero" :bg="$image ? $overlay : null" :tone="$image ? ($lightText ? 'dark' : 'light') : null" class="relative overflow-hidden">
    @if ($image)
        <x-site.picture :path="setting('hero_image')" :url="$image" alt="" :width="1600" :height="900"
            sizes="100vw" :lazy="false" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover" />
        <div class="absolute inset-0" style="background-color: {{ $overlay }}; opacity: {{ $opacity }};"
            aria-hidden="true"></div>
    @endif

    <div class="relative mx-auto max-w-4xl px-6 text-center {{ $padding }}">
        <span
            class="inline-block rounded-full bg-terracotta-dark px-3 py-1 text-xs font-semibold text-white">{{ __('site.hero.eyebrow', ['name' => setting('name', config('app.name'))]) }}</span>
        <h1 class="mt-5 text-4xl font-bold leading-tight sm:text-6xl">{{ setting('hero_title', __('site.hero.title')) }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg">{{ setting('hero_subtitle', __('site.hero.subtitle')) }}</p>
        <p class="mt-3 text-sm font-semibold uppercase tracking-widest text-(color:--accent)">{{ setting('cuisine') }}
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('menu.index') }}" class="btn-contrast">{{ __('site.hero.menu_cta') }}</a>
            <a href="{{ route('reservations.create') }}" class="btn-accent">{{ __('site.nav.reserve') }}</a>
        </div>
    </div>
</x-site.section>
