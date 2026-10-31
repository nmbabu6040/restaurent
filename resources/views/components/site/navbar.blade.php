@php
    $links = [
        ['route' => 'home', 'label' => __('site.nav.home')],
        ['route' => 'menu.index', 'label' => __('site.nav.menu')],
        ['route' => 'about', 'label' => __('site.nav.about')],
        ['route' => 'gallery', 'label' => __('site.nav.gallery')],
        ['route' => 'contact.create', 'label' => __('site.nav.contact')],
    ];
    $current = app()->getLocale();
    $brand = setting('name', config('app.name'));
    $logo = setting_image('logo_header');
    $pageLinks = \App\Models\Page::menu('header');
@endphp

<x-site.section name="header" as="header" class="sticky top-0 z-40 shadow-md">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-heading text-2xl font-bold tracking-wide">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $brand }}" height="60" class="h-12 w-auto">
            @else
                {{ $brand }}
            @endif
        </a>

        <nav aria-label="Primary" class="hidden items-center gap-8 lg:flex">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['route']); @endphp
                <a href="{{ route($link['route']) }}" @if ($active) aria-current="page" @endif
                    class="text-sm font-medium transition hover:text-(color:--accent) {{ $active ? 'text-(color:--accent)' : 'opacity-90' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach

            @foreach ($pageLinks as $p)
                <a href="{{ route('pages.show', $p->slug) }}"
                    @if (request()->is('page/' . $p->slug)) aria-current="page" @endif
                    class="text-sm font-medium opacity-90 transition hover:text-(color:--accent)">{{ $p->tr('title') }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <x-site.cart-link />
            <div class="flex items-center gap-1 text-sm" role="group" aria-label="Language">
                <a href="{{ route('lang.switch', 'en') }}" lang="en"
                    @if ($current === 'en') aria-current="true" @endif
                    class="rounded px-2 py-1 {{ $current === 'en' ? 'bg-current/15 font-semibold' : 'opacity-70 hover:opacity-100' }}">EN</a>
                <a href="{{ route('lang.switch', 'bn') }}" lang="bn"
                    @if ($current === 'bn') aria-current="true" @endif
                    class="rounded px-2 py-1 {{ $current === 'bn' ? 'bg-current/15 font-semibold' : 'opacity-70 hover:opacity-100' }}">বাং</a>
            </div>
            <a href="{{ route('reservations.create') }}" class="btn-accent">{{ __('site.nav.reserve') }}</a>
        </div>

        <div class="flex items-center gap-1 lg:hidden">
            <x-site.cart-link />
            <button id="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" class="rounded p-2"
                aria-label="{{ __('site.nav.toggle') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <div id="mobile-nav" class="hidden border-t border-current/10 px-6 pb-6 lg:hidden">
        <nav aria-label="Mobile" class="flex flex-col gap-1 pt-4">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                    class="rounded px-2 py-3 text-base hover:bg-current/10">{{ $link['label'] }}</a>
            @endforeach

            @foreach ($pageLinks as $p)
                <a href="{{ route('pages.show', $p->slug) }}"
                    @if (request()->is('page/' . $p->slug)) aria-current="page" @endif
                    class="text-sm font-medium opacity-90 transition hover:text-(color:--accent)">{{ $p->tr('title') }}</a>
            @endforeach
        </nav>
        <div class="mt-4 flex items-center justify-between">
            <div class="flex gap-2 text-sm">
                <a href="{{ route('lang.switch', 'en') }}" lang="en"
                    class="rounded px-3 py-2 {{ $current === 'en' ? 'bg-current/15 font-semibold' : '' }}">EN</a>
                <a href="{{ route('lang.switch', 'bn') }}" lang="bn"
                    class="rounded px-3 py-2 {{ $current === 'bn' ? 'bg-current/15 font-semibold' : '' }}">বাংলা</a>
            </div>
            <a href="{{ route('reservations.create') }}" class="btn-accent">{{ __('site.nav.reserve') }}</a>
        </div>
    </div>
</x-site.section>
