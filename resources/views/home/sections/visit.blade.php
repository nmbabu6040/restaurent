<x-site.section name="visit" aria-labelledby="visit-title">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <h2 id="visit-title" class="text-center text-3xl font-bold">{{ $section->heading('home.visit_title') }}</h2>
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card p-6">
                <h3 class="text-xl font-semibold">{{ __('home.hours') }}</h3>
                <p class="mt-3 text-sm">{!! nl2br(e(setting('hours', ''))) !!}</p>
            </div>
            <div class="card p-6">
                <h3 class="text-xl font-semibold">{{ __('home.address') }}</h3>
                <p class="mt-3 text-sm">{{ setting('address') }}</p>
                <p class="mt-3 text-sm">{{ __('home.call') }}: <a
                        href="tel:{{ preg_replace('/[^+\d]/', '', setting('phone', '')) }}"
                        class="font-semibold text-navy hover:text-terracotta-dark">{{ setting('phone') }}</a></p>
            </div>
        </div>
    </div>
</x-site.section>
