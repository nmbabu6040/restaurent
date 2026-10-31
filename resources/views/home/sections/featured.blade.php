@if ($featured->isNotEmpty())
    <x-site.section name="featured" aria-labelledby="featured-title">
        <div class="mx-auto max-w-7xl px-6 py-12">
            <div class="text-center">
                <h2 id="featured-title" class="text-3xl font-bold">{{ $section->heading('home.featured_title') }}</h2>
                <p class="mt-2">{{ $section->tr('subtitle') ?: __('home.featured_text') }}</p>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $item)
                    <x-menu.item-card :item="$item" heading="h3" />
                @endforeach
            </div>
            <div class="mt-8 text-center"><a href="{{ route('menu.index') }}"
                    class="btn-contrast">{{ __('home.view_menu') }}</a></div>
        </div>
    </x-site.section>
@endif
