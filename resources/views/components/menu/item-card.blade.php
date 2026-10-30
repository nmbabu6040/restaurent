@props(['item', 'heading' => 'h2'])

@php $canOrder = config('restaurant.ordering_enabled') && $item->is_available; @endphp

<article class="card relative flex flex-col overflow-hidden {{ $item->is_available ? '' : 'opacity-75' }}">
    <div class="relative aspect-[4/3] bg-navy/5">
        @if ($item->image_url)
            <x-site.picture :path="$item->image_path" :url="$item->image_url" :alt="$item->name"
                sizes="(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw" class="h-full w-full object-cover" />
        @else
            <div class="flex h-full items-center justify-center font-heading text-6xl text-navy/20" aria-hidden="true">
                {{ mb_substr($item->name, 0, 1) }}</div>
        @endif

        <div class="absolute left-3 top-3 flex gap-2">
            @if ($item->is_featured)
                <span
                    class="rounded-full bg-terracotta-dark px-2.5 py-1 text-xs font-semibold text-white">{{ __('menu.featured') }}</span>
            @endif
            @if ($item->is_special)
                <span
                    class="rounded-full bg-navy px-2.5 py-1 text-xs font-semibold text-white">{{ __('menu.special') }}</span>
            @endif

            @if (
                $item->stock_mode === 'quantity' &&
                    $item->is_available &&
                    $item->stock_qty > 0 &&
                    $item->stock_qty <= config('restaurant.inventory.low_dish_stock'))
                <span
                    class="rounded-full bg-red-700 px-2.5 py-1 text-xs font-semibold text-white">{{ __('menu.only_left', ['count' => $item->stock_qty]) }}</span>
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-dark">{{ $item->category->name }}</p>
        <{{ $heading }} class="mt-1 text-xl font-semibold">
            <a href="{{ route('menu.show', $item->slug) }}"
                class="after:absolute after:inset-0 hover:text-terracotta-dark">{{ $item->name }}</a>
            </{{ $heading }}>

            @if ($item->reviews_count > 0)
                <p class="mt-1 flex items-center gap-1 text-sm">
                    <x-site.stars :rating="$item->rating_avg" />
                    <span>{{ number_format((float) $item->rating_avg, 1) }} ({{ $item->reviews_count }})</span>
                </p>
            @endif

            <p class="mt-2 line-clamp-3 text-sm">{{ $item->description }}</p>

            @if (!empty($item->dietary))
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($item->dietary as $label)
                        <li class="rounded bg-warm px-2 py-0.5 text-xs text-charcoal">
                            {{ __('menu.dietary.' . $label) }}
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                <span class="font-heading text-xl font-bold text-navy">{{ $item->formatted_price }}</span>

                @if ($canOrder)
                    @if ($item->hasOptions())
                        <a href="{{ route('menu.show', $item->slug) }}"
                            class="btn-primary relative z-10 !px-4 !py-2">{{ __('orders.choose_options') }}</a>
                    @else
                        <form method="POST" action="{{ route('cart.add') }}" data-cart-add class="relative z-10">
                            @csrf
                            <input type="hidden" name="item_id" value="{{ $item->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn-primary !px-4 !py-2"
                                aria-label="{{ __('orders.add_named', ['name' => $item->name]) }}">{{ __('orders.add') }}</button>
                        </form>
                    @endif
                @elseif (!$item->is_available)
                    <span
                        class="rounded-full bg-charcoal/10 px-3 py-1 text-xs font-semibold">{{ __('menu.sold_out') }}</span>
                @endif
            </div>
    </div>
</article>
