@php
    // Full class names so Tailwind can find them. Every pair keeps white text readable.
    $palette = [
        'from-[#192F52] to-[#263D60]', // Navy Blue
        'from-[#A9512D] to-[#873F23]', // Terracotta
        'from-[#8F4B32] to-[#6F3828]', // Deep Copper
        'from-[#263D60] to-[#192F52]', // Deep Navy
        'from-[#A65E3A] to-[#803F28]', // Copper Brown
        'from-[#344B6D] to-[#192F52]', // Soft Navy
        'from-[#873F23] to-[#63301F]', // Dark Terracotta
        'from-[#6F493B] to-[#493027]', // Warm Brown
    ];
@endphp

@if ($categories->isNotEmpty())
    <x-site.section name="categories" aria-labelledby="cat-title">
        <div class="mx-auto max-w-7xl px-6 py-12">
            <h2 id="cat-title" class="text-center text-3xl font-bold">{{ $section->heading('home.categories_title') }}
            </h2>
            @if ($section->tr('subtitle'))
                <p class="mt-2 text-center">{{ $section->tr('subtitle') }}</p>
            @endif
            <ul class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                @foreach ($categories as $i => $category)
                    <li>
                        <a href="{{ route('menu.index', ['category' => $category->slug]) }}"
                            class="group relative flex h-full min-h-32 flex-col justify-between overflow-hidden rounded-2xl bg-linear-to-br {{ $palette[$i % count($palette)] }} p-4 text-white shadow-card transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline-2">
                            <span aria-hidden="true"
                                class="absolute -right-2 -top-5 font-heading text-8xl font-bold opacity-15">{{ mb_substr($category->name, 0, 1) }}</span>
                            <span
                                class="relative font-heading text-lg font-semibold leading-snug">{{ $category->name }}</span>
                            <span
                                class="relative mt-4 flex items-center justify-between text-xs font-medium text-white/90">
                                <span>{{ trans_choice('home.dish_count', $category->items_count, ['count' => $category->items_count]) }}</span>
                                <span aria-hidden="true"
                                    class="text-base transition group-hover:translate-x-1">&rarr;</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-site.section>
@endif
