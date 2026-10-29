@props(['title' => 'Deliveries', 'signature' => null])

@php $rider = auth()->user()->rider; @endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    @if ($favicon = setting_image('favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <title>{{ $title }} | Rider</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-dvh bg-warm">
    <a href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3 focus:text-navy">Skip to
        content</a>

    <header class="bg-[#172B4D] text-white">
        <div class="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('rider.dashboard') }}"
                class="font-heading text-lg font-bold">{{ setting('name', config('app.name')) }} &middot; Rider</a>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('rider.online') }}">
                    @csrf @method('PATCH')
                    <button type="submit" aria-pressed="{{ $rider->is_online ? 'true' : 'false' }}"
                        class="rounded-full px-4 py-2 text-sm font-semibold {{ $rider->is_online ? 'bg-green-700 text-white' : 'bg-white/20 text-white' }}">
                        {{ $rider->is_online ? 'Online' : 'Offline' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full bg-white/20 px-4 py-2 text-sm font-semibold">Log
                        out</button>
                </form>
            </div>
        </div>
    </header>

    <main id="main" tabindex="-1" class="mx-auto max-w-2xl p-4 focus:outline-none"
        @if ($signature !== null) data-rider-poll-url="{{ route('rider.poll') }}" data-signature="{{ $signature }}" @endif>
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
        {{ $slot }}
    </main>

    @if (session('status'))
        <div id="flash-message" data-message="{{ session('status') }}" hidden></div>
    @elseif (session('error'))
        <div id="flash-message" data-message="{{ session('error') }}" data-type="error" hidden></div>
    @endif
</body>

</html>
