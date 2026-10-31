@if ($testimonials->isNotEmpty())
    <x-site.section name="testimonials" aria-labelledby="testimonials-title">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <h2 id="testimonials-title" class="text-center text-3xl font-bold">
                {{ $section->heading('home.testimonials_title') }}</h2>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($testimonials as $t)
                    <figure class="on-light rounded-2xl bg-warm p-6">
                        <p role="img" aria-label="{{ __('home.stars', ['count' => $t->rating]) }}"
                            class="text-terracotta-dark"><span
                                aria-hidden="true">{{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}</span>
                        </p>
                        <blockquote class="mt-3 text-sm">“{{ $t->tr('quote') }}”</blockquote>
                        <figcaption class="mt-4 text-sm font-semibold text-navy">{{ $t->name }}@if ($t->tr('title'))
                                <span class="block text-xs font-normal text-charcoal/70">{{ $t->tr('title') }}</span>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </x-site.section>
@endif
