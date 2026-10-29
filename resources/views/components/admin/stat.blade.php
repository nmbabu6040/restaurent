@props(['label', 'value', 'icon' => 'chart', 'tone' => 'indigo', 'sub' => null, 'change' => null, 'href' => null])

@php
    // Full class names on purpose: Tailwind only builds classes it can find written out.
    $tones = [
        'indigo' => 'from-indigo-600 to-violet-700',
        'emerald' => 'from-emerald-700 to-teal-700',
        'amber' => 'from-amber-700 to-orange-700',
        'rose' => 'from-rose-600 to-pink-700',
        'cyan' => 'from-cyan-700 to-sky-700',
        'violet' => 'from-violet-600 to-purple-800',
        'pink' => 'from-pink-600 to-rose-700',
        'teal' => 'from-teal-700 to-emerald-800',
    ];

    $icons = [
        'cash' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'bag' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'receipt' => '<path d="M4 2v20l3-2 2 2 3-2 3 2 2-2 3 2V2l-3 2-2-2-3 2-3-2-2 2zM8 8h8M8 12h8"/>',
        'trend' => '<path d="m22 7-8.5 8.5-5-5L2 17M16 7h6v6"/>',
        'chart' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
        'users' =>
            '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'utensils' =>
            '<path d="M3 2v7c0 1.1.9 2 2 2h2a2 2 0 0 0 2-2V2M6 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2zM18 15v7"/>',
        'tag' =>
            '<path d="M19 5 5 19M6.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5M17.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>',
        'truck' =>
            '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7zM5.5 21a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5M18.5 21a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>',
        'repeat' => '<path d="m17 2 4 4-4 4M3 11v-1a4 4 0 0 1 4-4h14M7 22l-4-4 4-4M21 13v1a4 4 0 0 1-4 4H3"/>',
    ];

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    class="relative block overflow-hidden rounded-2xl bg-linear-to-br {{ $tones[$tone] ?? $tones['indigo'] }} p-5 text-white shadow-card {{ $href ? 'transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline-2' : '' }}">
    <svg class="absolute -right-3 -top-3 h-24 w-24 opacity-15" fill="none" stroke="currentColor" stroke-width="1.5"
        stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$icon] ?? $icons['chart'] !!}</svg>

    <p class="relative text-sm font-medium text-white/90">{{ $label }}</p>
    <p class="relative mt-1 font-heading text-3xl font-bold">{{ $value }}</p>

    @if ($change !== null || $sub)
        <p class="relative mt-2 flex flex-wrap items-center gap-2 text-xs text-white/90">
            @if ($change !== null)
                <span class="rounded-full bg-white/20 px-2 py-0.5 font-semibold">
                    <span aria-hidden="true">{{ $change >= 0 ? '▲' : '▼' }}</span>
                    <span class="sr-only">{{ $change >= 0 ? 'Up' : 'Down' }}</span>
                    {{ number_format(abs($change), 1) }}%
                </span>
            @endif
            @if ($sub)
                <span>{{ $sub }}</span>
            @endif
        </p>
    @endif
    </{{ $tag }}>
