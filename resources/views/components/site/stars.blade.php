@props(['rating'])

@php $full = (int) round((float) $rating); @endphp

<span role="img" aria-label="{{ __('reviews.stars', ['rating' => number_format((float) $rating, 1)]) }}"
    class="text-amber-700">
    <span aria-hidden="true">{{ str_repeat('★', $full) }}{{ str_repeat('☆', 5 - $full) }}</span>
</span>
