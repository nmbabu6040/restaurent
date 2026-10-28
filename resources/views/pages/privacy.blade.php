<x-layouts.app :title="__('pages.privacy.title')">
    <x-site.page-header :title="__('pages.privacy.title')" />
    <section class="mx-auto max-w-3xl space-y-8 px-6 py-12">
        @foreach (__('pages.privacy.sections') as $section)
            <div>
                <h2 class="text-2xl font-semibold">{{ $section['h'] }}</h2>
                <p class="mt-2">{{ $section['p'] }}</p>
            </div>
        @endforeach
    </section>
</x-layouts.app>
