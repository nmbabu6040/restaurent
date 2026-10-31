@if ($promotions->isNotEmpty())
    <x-site.section name="offers" aria-labelledby="offers-title">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <h2 id="offers-title" class="text-center text-3xl font-bold">{{ $section->heading('home.offers_title') }}</h2>
            @if ($section->tr('subtitle'))
                <p class="mt-2 text-center">{{ $section->tr('subtitle') }}</p>
            @endif
            <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($promotions as $promo)
                    <article class="overflow-hidden rounded-2xl border border-current/15 bg-current/5">
                        @if ($promo->image_url)
                            <img src="{{ $promo->image_url }}" alt="" width="640" height="360" loading="lazy"
                                class="aspect-video w-full object-cover">
                        @endif
                        <div class="p-6">
                            <h3 class="text-xl font-semibold">{{ $promo->tr('title') }}</h3>
                            <p class="mt-2 text-sm opacity-80">{{ $promo->tr('description') }}</p>
                            @if ($promo->ends_at)
                                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-(color:--accent)">
                                    {{ __('home.valid_until', ['date' => $promo->ends_at->translatedFormat('d M Y')]) }}
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </x-site.section>
@endif
