<x-layouts.app :title="__('pages.offers.title')" :description="__('pages.offers.subtitle')">
    <x-site.page-header :title="__('pages.offers.title')" :subtitle="__('pages.offers.subtitle')" />

    <section class="mx-auto max-w-5xl px-6 py-12">
        @if ($promotions->isEmpty())
            <p class="text-center">{{ __('pages.offers.empty') }}</p>
        @else
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($promotions as $promo)
                    <article class="card overflow-hidden">
                        @if ($promo->image_url)
                            <img src="{{ $promo->image_url }}" alt="" width="640" height="360" loading="lazy"
                                class="aspect-video w-full object-cover">
                        @endif
                        <div class="p-6">
                            <h2 class="text-2xl font-semibold">{{ $promo->tr('title') }}</h2>
                            <p class="mt-2">{{ $promo->tr('description') }}</p>
                            @if ($promo->ends_at)
                                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-terracotta-dark">
                                    {{ __('home.valid_until', ['date' => $promo->ends_at->translatedFormat('d M Y')]) }}
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-10 text-center">
                <a href="{{ route('menu.index') }}" class="btn-primary">{{ __('pages.offers.cta') }}</a>
            </div>
        @endif
    </section>
</x-layouts.app>
