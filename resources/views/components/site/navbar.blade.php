@php
    $links = [
        ['route' => 'home', 'label' => __('site.nav.home')],
        ['route' => 'menu.index', 'label' => __('site.nav.menu')],
        ['route' => 'about', 'label' => __('site.nav.about')],
        ['route' => 'gallery', 'label' => __('site.nav.gallery')],
        ['route' => 'contact.create', 'label' => __('site.nav.contact')],
    ];
    $reserveUrl = Route::has('reservations.create') ? route('reservations.create') : '#';
    $current = app()->getLocale();
@endphp

<header class="sticky top-0 z-40 bg-navy text-white shadow-md">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
        <a href="{{ route('home') }}"
            class="font-heading text-2xl font-bold tracking-wide text-white">{{ setting('name', config('app.name')) }}</a>

        <nav aria-label="Primary" class="hidden items-center gap-8 lg:flex">
            @foreach ($links as $link)
                <a href="{{ Route::has($link['route']) ? route($link['route']) : '#' }}"
                    @if (request()->routeIs($link['route'])) aria-current="page" @endif
                    class="text-sm font-medium transition hover:text-terracotta-light {{ request()->routeIs($link['route']) ? 'text-terracotta-light' : 'text-white/90' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <x-site.cart-link />
            <div class="flex items-center gap-1 text-sm" role="group" aria-label="Language">
                <a href="{{ route('lang.switch', 'en') }}" lang="en"
                    @if ($current === 'en') aria-current="true" @endif
                    class="rounded px-2 py-1 {{ $current === 'en' ? 'bg-white/15 font-semibold' : 'text-white/70 hover:text-white' }}">EN</a>
                <a href="{{ route('lang.switch', 'bn') }}" lang="bn"
                    @if ($current === 'bn') aria-current="true" @endif
                    class="rounded px-2 py-1 {{ $current === 'bn' ? 'bg-white/15 font-semibold' : 'text-white/70 hover:text-white' }}">বাং</a>
            </div>
            <a href="{{ $reserveUrl }}" class="btn-accent">{{ __('site.nav.reserve') }}</a>
        </div>

        <div class="flex items-center gap-1 lg:hidden">
            <x-site.cart-link />

            <button id="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav"
                class="rounded p-2 lg:hidden" aria-label="{{ __('site.nav.toggle') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <div id="mobile-nav" class="hidden border-t border-white/10 px-6 pb-6 lg:hidden">
        <nav aria-label="Mobile" class="flex flex-col gap-1 pt-4">
            @foreach ($links as $link)
                <a href="{{ Route::has($link['route']) ? route($link['route']) : '#' }}"
                    class="rounded px-2 py-3 text-base hover:bg-white/10">{{ $link['label'] }}</a>
            @endforeach
        </nav>
        <div class="mt-4 flex items-center justify-between">
            <div class="flex gap-2 text-sm">
                <a href="{{ route('lang.switch', 'en') }}" lang="en"
                    class="rounded px-3 py-2 {{ $current === 'en' ? 'bg-white/15 font-semibold' : '' }}">EN</a>
                <a href="{{ route('lang.switch', 'bn') }}" lang="bn"
                    class="rounded px-3 py-2 {{ $current === 'bn' ? 'bg-white/15 font-semibold' : '' }}">বাংলা</a>
            </div>
            <a href="{{ $reserveUrl }}" class="btn-accent">{{ __('site.nav.reserve') }}</a>
        </div>
    </div>
</header>
