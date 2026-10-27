@props(['item'])

<article class="card relative flex flex-col overflow-hidden {{ $item->is_available ? '' : 'opacity-75' }}">
    <div class="relative aspect-[4/3] bg-navy/5">
        @if ($item->image_url)
            <img src="{{ $item->image_url }}" alt="{{ $item->name }}" width="640" height="480" loading="lazy"
                class="h-full w-full object-cover">
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
        </div>
    </div>

    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-dark">{{ $item->category->name }}</p>
        <h2 class="mt-1 text-xl font-semibold">
            <a href="{{ route('menu.show', $item->slug) }}"
                class="after:absolute after:inset-0 hover:text-terracotta-dark">{{ $item->name }}</a>
        </h2>
        <p class="mt-2 line-clamp-3 text-sm">{{ $item->description }}</p>

        @if (!empty($item->dietary))
            <ul class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($item->dietary as $label)
                    <li class="rounded bg-warm px-2 py-0.5 text-xs text-charcoal">{{ __('menu.dietary.' . $label) }}
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-auto flex items-center justify-between pt-4">
            <span class="font-heading text-xl font-bold text-navy">{{ $item->formatted_price }}</span>
            @unless ($item->is_available)
                <span class="rounded-full bg-charcoal/10 px-3 py-1 text-xs font-semibold">{{ __('menu.sold_out') }}</span>
            @endunless
        </div>
    </div>
</article>
