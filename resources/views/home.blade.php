@php
    $menuUrl = Route::has('menu.index') ? route('menu.index') : '#';
    $reserveUrl = Route::has('reservations.create') ? route('reservations.create') : '#';
    $brand = setting('name', config('app.name'));
@endphp

<x-layouts.app :description="setting('hero_subtitle')">
    {{-- Hero --}}
    <section class="mx-auto max-w-4xl px-6 py-20 text-center sm:py-28">
        <span class="inline-block rounded-full bg-terracotta-dark px-3 py-1 text-xs font-semibold text-white">
            {{ __('site.hero.eyebrow', ['name' => $brand]) }}
        </span>
        <h1 class="mt-5 text-4xl font-bold leading-tight sm:text-6xl">{{ setting('hero_title', __('site.hero.title')) }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg">{{ setting('hero_subtitle', __('site.hero.subtitle')) }}</p>
        <p class="mt-3 text-sm font-semibold uppercase tracking-widest text-terracotta-dark">{{ setting('cuisine') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ $menuUrl }}" class="btn-primary">{{ __('site.hero.menu_cta') }}</a>
            <a href="{{ $reserveUrl }}" class="btn-accent">{{ __('site.nav.reserve') }}</a>
        </div>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-6 py-12" aria-labelledby="cat-title">
            <h2 id="cat-title" class="text-center text-3xl font-bold">{{ __('home.categories_title') }}</h2>
            <ul class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('menu.index', ['category' => $category->slug]) }}"
                            class="card flex h-full items-center justify-center p-5 text-center font-heading text-lg text-navy transition hover:-translate-y-0.5 hover:text-terracotta-dark">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Featured dishes --}}
    @if ($featured->isNotEmpty())
        <section class="mx-auto max-w-7xl px-6 py-12" aria-labelledby="featured-title">
            <div class="text-center">
                <h2 id="featured-title" class="text-3xl font-bold">{{ __('home.featured_title') }}</h2>
                <p class="mt-2">{{ __('home.featured_text') }}</p>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $item)
                    <x-menu.item-card :item="$item" />
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ $menuUrl }}" class="btn-primary">{{ __('home.view_menu') }}</a>
            </div>
        </section>
    @endif

    {{-- Introduction and USPs --}}
    <section class="bg-surface py-16" aria-labelledby="intro-title">
        <div class="mx-auto max-w-5xl px-6 text-center">
            <h2 id="intro-title" class="text-3xl font-bold">{{ __('home.intro_title') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl">{{ __('home.intro_text') }}</p>
            <ul class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach (__('home.usp') as $usp)
                    <li>
                        <h3 class="text-xl font-semibold">{{ $usp['title'] }}</h3>
                        <p class="mt-2 text-sm">{{ $usp['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Chef's recommendations --}}
    @if ($chefPicks->isNotEmpty())
        <section class="mx-auto max-w-7xl px-6 py-14" aria-labelledby="chef-title">
            <div class="text-center">
                <h2 id="chef-title" class="text-3xl font-bold">{{ __('home.chef_title') }}</h2>
                <p class="mt-2">{{ __('home.chef_text') }}</p>
            </div>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($chefPicks as $item)
                    <x-menu.item-card :item="$item" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Offers --}}
    @if ($promotions->isNotEmpty())
        <section class="bg-navy py-14 text-white" aria-labelledby="offers-title">
            <div class="mx-auto max-w-7xl px-6">
                <h2 id="offers-title" class="text-center text-3xl font-bold !text-white">{{ __('home.offers_title') }}
                </h2>
                <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($promotions as $promo)
                        <article class="overflow-hidden rounded-2xl border border-white/15 bg-white/5">
                            @if ($promo->image_url)
                                <img src="{{ $promo->image_url }}" alt="" width="640" height="360"
                                    loading="lazy" class="aspect-video w-full object-cover">
                            @endif
                            <div class="p-6">
                                <h3 class="text-xl font-semibold !text-white">{{ $promo->tr('title') }}</h3>
                                <p class="mt-2 text-sm text-white/80">{{ $promo->tr('description') }}</p>
                                @if ($promo->ends_at)
                                    <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-terracotta">
                                        {{ __('home.valid_until', ['date' => $promo->ends_at->translatedFormat('d M Y')]) }}
                                    </p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Gallery --}}
    @if ($gallery->isNotEmpty())
        <section class="mx-auto max-w-7xl px-6 py-14" aria-labelledby="gallery-title">
            <h2 id="gallery-title" class="text-center text-3xl font-bold">{{ __('home.gallery_title') }}</h2>
            <ul class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($gallery as $image)
                    <li class="overflow-hidden rounded-xl">
                        <img src="{{ $image->image_url }}" alt="{{ $image->tr('alt') }}" width="480" height="480"
                            loading="lazy"
                            class="aspect-square w-full object-cover transition duration-300 hover:scale-105">
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Testimonials --}}
    @if ($testimonials->isNotEmpty())
        <section class="bg-surface py-14" aria-labelledby="testimonials-title">
            <div class="mx-auto max-w-7xl px-6">
                <h2 id="testimonials-title" class="text-center text-3xl font-bold">{{ __('home.testimonials_title') }}
                </h2>
                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    @foreach ($testimonials as $t)
                        <figure class="rounded-2xl bg-warm p-6">
                            <p role="img" aria-label="{{ __('home.stars', ['count' => $t->rating]) }}"
                                class="text-terracotta-dark">
                                <span
                                    aria-hidden="true">{{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}</span>
                            </p>
                            <blockquote class="mt-3 text-sm">“{{ $t->tr('quote') }}”</blockquote>
                            <figcaption class="mt-4 text-sm font-semibold text-navy">
                                {{ $t->name }}
                                @if ($t->tr('title'))
                                    <span
                                        class="block text-xs font-normal text-charcoal/70">{{ $t->tr('title') }}</span>
                                @endif
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Hours and location --}}
    <section class="mx-auto max-w-5xl px-6 py-14" aria-labelledby="visit-title">
        <h2 id="visit-title" class="text-center text-3xl font-bold">{{ __('home.visit_title') }}</h2>
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card p-6">
                <h3 class="text-xl font-semibold">{{ __('home.hours') }}</h3>
                <p class="mt-3 text-sm">{!! nl2br(e(setting('hours', ''))) !!}</p>
            </div>
            <div class="card p-6">
                <h3 class="text-xl font-semibold">{{ __('home.address') }}</h3>
                <p class="mt-3 text-sm">{{ setting('address') }}</p>
                <p class="mt-3 text-sm">
                    {{ __('home.call') }}:
                    <a href="tel:{{ preg_replace('/[^+\d]/', '', setting('phone', '')) }}"
                        class="font-semibold text-navy hover:text-terracotta-dark">{{ setting('phone') }}</a>
                </p>
            </div>
        </div>
    </section>

    {{-- Reservation CTA --}}
    <section class="bg-terracotta-dark py-14 text-center text-white" aria-labelledby="reserve-title">
        <div class="mx-auto max-w-2xl px-6">
            <h2 id="reserve-title" class="text-3xl font-bold !text-white">{{ __('home.reserve_title') }}</h2>
            <p class="mt-3 text-white/90">{{ __('home.reserve_text') }}</p>
            <a href="{{ $reserveUrl }}"
                class="btn mt-6 bg-white text-navy hover:bg-warm">{{ __('site.nav.reserve') }}</a>
        </div>
    </section>
</x-layouts.app>
