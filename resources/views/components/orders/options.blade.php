@props(['variation' => null, 'addons' => []])

@php
    $parts = [];
    if ($variation) {
        $parts[] = $variation->tr('name');
    }
    foreach ($addons as $addon) {
        $parts[] = '+ ' . $addon->tr('name');
    }
@endphp

@if ($parts)
    <span class="block text-xs text-charcoal/80">{{ implode(', ', $parts) }}</span>
@endif
