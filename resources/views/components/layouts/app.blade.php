@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'noindex' => false,
    'canonical' => null,
    'type' => 'website',
])

@php
    $siteName = setting('name', config('app.name'));
    $metaDescription = $description ?: setting('tagline', '');
    $page = (int) request('page', 1);
    $canonicalUrl = $canonical ?: url()->current() . ($page > 1 ? '?page=' . $page : '');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#172B4D">
    <title>{{ $title ? $title . ' | ' : '' }}{{ $siteName }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($noindex)
        <meta name="robots" content="noindex,nofollow">
    @else
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?: $siteName }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'bn' ? 'bn_BD' : 'en_US' }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">

    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen flex-col" data-cart-error="{{ __('forms.common.error') }}">
    <a href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">{{ __('site.skip') }}</a>

    <x-site.navbar />

    <main id="main" tabindex="-1" class="flex-1 focus:outline-none">{{ $slot }}</main>

    <x-site.footer />

    @if (session('status'))
        <div id="flash-message" data-message="{{ session('status') }}" hidden></div>
    @elseif (session('error'))
        <div id="flash-message" data-message="{{ session('error') }}" data-type="error" hidden></div>
    @endif
</body>

</html>
