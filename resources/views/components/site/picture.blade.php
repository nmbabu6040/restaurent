@props(['path', 'url', 'alt' => '', 'width' => 640, 'height' => 480, 'sizes' => '100vw', 'lazy' => true])

@php $thumb = image_thumb($path); @endphp

<img src="{{ $url }}"
    @if ($thumb) srcset="{{ $thumb }} 480w, {{ $url }} 1600w" sizes="{{ $sizes }}" @endif
    alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}"
    @if ($lazy) loading="lazy" decoding="async" @endif {{ $attributes }}>
