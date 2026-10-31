@if ($chefPicks->isNotEmpty())
    <x-site.section name="chef" aria-labelledby="chef-title">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <div class="text-center">
                <h2 id="chef-title" class="text-3xl font-bold">{{ $section->heading('home.chef_title') }}</h2>
                <p class="mt-2">{{ $section->tr('subtitle') ?: __('home.chef_text') }}</p>
            </div>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($chefPicks as $item)
                    <x-menu.item-card :item="$item" heading="h3" />
                @endforeach
            </div>
        </div>
    </x-site.section>
@endif
