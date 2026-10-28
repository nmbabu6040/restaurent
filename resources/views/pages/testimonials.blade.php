<x-layouts.app :title="__('pages.testimonials.title')" :description="__('pages.testimonials.subtitle')">
    <x-site.page-header :title="__('pages.testimonials.title')" :subtitle="__('pages.testimonials.subtitle')" />

    <section class="mx-auto max-w-6xl px-6 py-12">
        @if ($testimonials->isEmpty())
            <p class="text-center">{{ __('pages.testimonials.empty') }}</p>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $t)
                    <figure class="card p-6">
                        <p role="img" aria-label="{{ __('home.stars', ['count' => $t->rating]) }}"
                            class="text-terracotta-dark">
                            <span
                                aria-hidden="true">{{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}</span>
                        </p>
                        <blockquote class="mt-3 text-sm">“{{ $t->tr('quote') }}”</blockquote>
                        <figcaption class="mt-4 text-sm font-semibold text-navy">
                            {{ $t->name }}
                            @if ($t->tr('title'))
                                <span class="block text-xs font-normal text-charcoal/70">{{ $t->tr('title') }}</span>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
