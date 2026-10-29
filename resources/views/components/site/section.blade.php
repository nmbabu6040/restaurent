@props(['name', 'as' => 'section'])

@php $theme = section_theme($name); @endphp

<{{ $as }} {{ $attributes->merge(['class' => 'section-themed']) }}
    data-tone="{{ $theme['dark'] ? 'dark' : 'light' }}" style="--sec-bg: {{ $theme['bg'] }};">{{ $slot }}
    </{{ $as }}>
