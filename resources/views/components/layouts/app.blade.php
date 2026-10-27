<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' | ' : '' }}{{ setting('name', config('app.name')) }}</title>
    <meta name="description" content="{{ $description ?? '' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen flex-col">
    <a href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">{{ __('site.skip') }}</a>

    <x-site.navbar />

    <main id="main" class="flex-1">{{ $slot }}</main>

    <x-site.footer />

    @if (session('status'))
        <div id="flash-message" data-message="{{ session('status') }}" hidden></div>
    @endif
</body>

</html>
