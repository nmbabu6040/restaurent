@props(['name' => null, 'as' => 'section', 'bg' => null, 'tone' => null])

@php
    $theme =
        $bg && \App\Support\Color::isHex($bg)
            ? ['bg' => strtoupper($bg), 'dark' => \App\Support\Color::wantsLightText($bg)]
            : section_theme($name ?? 'custom');
    $dark = $tone !== null ? $tone === 'dark' : $theme['dark'];
@endphp

<{{ $as }} {{ $attributes->merge(['class' => 'section-themed']) }} data-tone="{{ $dark ? 'dark' : 'light' }}"
    style="--sec-bg: {{ $theme['bg'] }};">{{ $slot }}</{{ $as }}>
