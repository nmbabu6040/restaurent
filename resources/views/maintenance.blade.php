@php
    $brand = setting('name', config('app.name'));
    $logo = setting_image('logo_header');
    $message = setting('maintenance_message') ?: __('site.maintenance.text');
    $favicon = setting_image('favicon');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('site.maintenance.title') }} | {{ $brand }}</title>
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif
    @vite(['resources/css/app.css'])
</head>

<body class="flex min-h-screen items-center justify-center bg-warm p-6 text-center">
    <main class="card max-w-lg p-10">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $brand }}" class="mx-auto h-14 w-auto">
        @else
            <p class="font-heading text-3xl font-bold text-navy">{{ $brand }}</p>
        @endif

        <h1 class="mt-6 text-3xl font-bold">{{ __('site.maintenance.title') }}</h1>
        <p class="mt-3">{{ $message }}</p>

        @if (setting('phone'))
            <p class="mt-6 text-sm">{{ __('site.maintenance.contact') }}
                <a href="tel:{{ preg_replace('/[^+\d]/', '', setting('phone', '')) }}"
                    class="font-semibold text-navy hover:text-terracotta-dark">{{ setting('phone') }}</a>
            </p>
        @endif

        <p class="mt-6 text-sm">
            <a href="{{ route('lang.switch', 'en') }}"
                class="font-semibold text-navy hover:text-terracotta-dark">English</a> &middot;
            <a href="{{ route('lang.switch', 'bn') }}"
                class="font-semibold text-navy hover:text-terracotta-dark">বাংলা</a>
        </p>
    </main>
</body>

</html>
