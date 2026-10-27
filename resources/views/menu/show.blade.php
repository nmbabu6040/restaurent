<x-layouts.app :title="$item->name" :description="\Illuminate\Support\Str::limit(strip_tags((string) $item->description), 150)">
    <article class="mx-auto max-w-6xl px-6 py-12">
        <nav aria-label="Breadcrumb" class="text-sm">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ route('menu.index') }}" class="hover:text-terracotta-dark">{{ __('site.nav.menu') }}</a>
                </li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('menu.index', ['category' => $item->category->slug]) }}"
                        class="hover:text-terracotta-dark">{{ $item->category->name }}</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-semibold text-navy">{{ $item->name }}</li>
            </ol>
        </nav>

        <div class="mt-8 grid gap-10 md:grid-cols-2">
            <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-navy/5">
                @if ($item->image_url)
                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" width="800" height="600"
                        class="h-full w-full object-cover">
                @else
                    <div class="flex h-full items-center justify-center font-heading text-8xl text-navy/20"
                        aria-hidden="true">{{ mb_substr($item->name, 0, 1) }}</div>
                @endif
            </div>

            <div>
                <div class="flex flex-wrap gap-2">
                    @if ($item->is_featured)
                        <span
                            class="rounded-full bg-terracotta-dark px-2.5 py-1 text-xs font-semibold text-white">{{ __('menu.featured') }}</span>
                    @endif
                    @if ($item->is_special)
                        <span
                            class="rounded-full bg-navy px-2.5 py-1 text-xs font-semibold text-white">{{ __('menu.special') }}</span>
                    @endif
                </div>

                <h1 class="mt-3 text-4xl font-bold">{{ $item->name }}</h1>
                <p class="mt-4 font-heading text-3xl font-bold text-navy">{{ $item->formatted_price }}</p>

                <p
                    class="mt-2 text-sm font-semibold {{ $item->is_available ? 'text-green-800' : 'text-charcoal/70' }}">
                    {{ $item->is_available ? __('menu.available') : __('menu.sold_out') }}
                </p>

                <p class="mt-5">{{ $item->description }}</p>

                @if (!empty($item->dietary))
                    <ul class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($item->dietary as $label)
                            <li class="rounded bg-surface px-2 py-0.5 text-xs">{{ __('menu.dietary.' . $label) }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($item->tr('ingredients'))
                    <h2 class="mt-8 text-xl font-semibold">{{ __('menu.ingredients') }}</h2>
                    <p class="mt-2 text-sm">{{ $item->tr('ingredients') }}</p>
                @endif

                @if ($item->tr('allergens'))
                    <h2 class="mt-6 text-xl font-semibold">{{ __('menu.allergens') }}</h2>
                    <p class="mt-2 text-sm">{{ $item->tr('allergens') }}</p>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16" aria-labelledby="related-title">
                <h2 id="related-title" class="text-2xl font-bold">{{ __('menu.related') }}</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $rel)
                        <x-menu.item-card :item="$rel" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layouts.app>
