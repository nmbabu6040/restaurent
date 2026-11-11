<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>Kitchen | {{ setting('name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-dvh bg-slate-950 text-white">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <h1 class="font-heading text-2xl font-bold text-white">Kitchen</h1>
        <p class="text-sm text-slate-300"><span data-ticket-count>{{ $tickets->count() }}</span> waiting &middot; <span
                class="text-emerald-400">under 10 min</span> &middot; <span class="text-amber-400">10 to 20</span>
            &middot; <span class="text-red-400">over 20</span></p>
        <div class="flex items-center gap-3 text-sm">
            <button type="button" data-kitchen-sound aria-pressed="false"
                class="rounded-full bg-white/15 px-4 py-2 font-semibold hover:bg-white/25"><span
                    data-kitchen-sound-label>Sound: off</span></button>
            <a href="{{ route('admin.dashboard') }}"
                class="rounded-full bg-white/15 px-4 py-2 font-semibold hover:bg-white/25">Admin</a>
        </div>
    </header>

    <main id="kitchen-board" data-url="{{ route('admin.kitchen.tickets') }}"
        class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('admin.kitchen._tickets')
    </main>
</body>

</html>
