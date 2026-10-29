<x-layouts.app :title="__('pages.errors.419.title')" :noindex="true">
    <section class="mx-auto max-w-xl px-6 py-24 text-center">
        <p class="font-heading text-7xl font-bold text-terracotta-dark" aria-hidden="true">419</p>
        <h1 class="mt-4 text-3xl font-bold">{{ __('pages.errors.419.title') }}</h1>
        <p class="mt-3">{{ __('pages.errors.419.text') }}</p>
        <a href="{{ route('home') }}" class="btn-primary mt-8">{{ __('pages.errors.home') }}</a>
    </section>
</x-layouts.app>
