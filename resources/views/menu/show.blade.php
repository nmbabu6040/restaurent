<x-layouts.app :title="$item->name" :description="\Illuminate\Support\Str::limit(strip_tags((string) $item->description), 150)" :image="$item->image_url">

    @push('head')
        @php
            $diets = ['vegetarian' => 'https://schema.org/VegetarianDiet', 'halal' => 'https://schema.org/HalalDiet'];
            $lowest = $item->activeVariations->min('price') ?? $item->price;
            $ld = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'MenuItem',
                'name' => $item->name,
                'description' => $item->description,
                'image' => $item->image_url,
                'suitableForDiet' =>
                    array_values(array_intersect_key($diets, array_flip($item->dietary ?? []))) ?: null,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format((float) $lowest, 2, '.', ''),
                    'priceCurrency' => 'BDT',
                    'availability' => $item->is_available
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                ],
            ]);
        @endphp
        <x-seo.json-ld :data="$ld" />
    @endpush

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

                @if (config('restaurant.ordering_enabled') && $item->is_available)
                    <form method="POST" action="{{ route('cart.add') }}" data-cart-add data-item-options
                        data-base="{{ $item->price }}" data-currency="{{ config('restaurant.currency_symbol') }}"
                        class="mt-6 space-y-5">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">

                        @if ($item->activeVariations->isNotEmpty())
                            <fieldset>
                                <legend class="text-sm font-medium text-navy">{{ __('orders.choose_size') }}<span
                                        aria-hidden="true" class="text-terracotta-dark"> *</span></legend>
                                <div class="mt-2 space-y-2">
                                    @foreach ($item->activeVariations as $i => $variation)
                                        <label
                                            class="flex items-center justify-between gap-3 rounded-lg border border-navy/20 bg-surface px-4 py-3 text-sm">
                                            <span class="flex items-center gap-2">
                                                <input type="radio" name="variation_id" value="{{ $variation->id }}"
                                                    data-price="{{ $variation->price }}" required class="accent-navy"
                                                    @checked($i === 0)>
                                                {{ $variation->tr('name') }}
                                            </span>
                                            <span class="font-semibold text-navy">{{ money($variation->price) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        @if ($item->activeAddons->isNotEmpty())
                            <fieldset>
                                <legend class="text-sm font-medium text-navy">{{ __('orders.extras') }}</legend>
                                <div class="mt-2 space-y-2">
                                    @foreach ($item->activeAddons as $addon)
                                        <label
                                            class="flex items-center justify-between gap-3 rounded-lg border border-navy/20 bg-surface px-4 py-3 text-sm">
                                            <span class="flex items-center gap-2">
                                                <input type="checkbox" name="addons[]" value="{{ $addon->id }}"
                                                    data-price="{{ $addon->price }}" class="accent-navy">
                                                {{ $addon->tr('name') }}
                                            </span>
                                            <span class="font-semibold text-navy">+ {{ money($addon->price) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label for="quantity"
                                    class="block text-sm font-medium text-navy">{{ __('orders.quantity') }}</label>
                                <input id="quantity" type="number" name="quantity" value="1" min="1"
                                    max="{{ config('restaurant.ordering.max_qty') }}"
                                    class="mt-1 w-24 rounded-lg border border-navy/20 bg-surface px-3 py-2.5 text-sm focus:border-terracotta">
                            </div>
                            <p class="text-sm">{{ __('orders.item_total') }}:
                                <strong id="option-total" aria-live="polite"
                                    class="font-heading text-xl text-navy">{{ money($item->activeVariations->first()?->price ?? $item->price) }}</strong>
                            </p>
                            <button type="submit" class="btn-accent">{{ __('orders.add') }}</button>
                        </div>
                    </form>
                @endif

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
                <h3 id="related-title" class="text-2xl font-bold">{{ __('menu.related') }}</h3>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $rel)
                        <x-menu.item-card :item="$rel" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layouts.app>
