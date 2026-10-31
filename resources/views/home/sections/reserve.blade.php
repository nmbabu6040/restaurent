<x-site.section name="reserve" aria-labelledby="reserve-title">
    <div class="mx-auto max-w-2xl px-6 py-14 text-center">
        <h2 id="reserve-title" class="text-3xl font-bold">{{ $section->heading('home.reserve_title') }}</h2>
        <p class="mt-3 opacity-90">{{ $section->tr('subtitle') ?: __('home.reserve_text') }}</p>
        <a href="{{ route('reservations.create') }}" class="btn-contrast mt-6">{{ __('site.nav.reserve') }}</a>
    </div>
</x-site.section>
