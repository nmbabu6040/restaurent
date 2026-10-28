@props(['title' => 'Dashboard'])

@php
    $pending = \App\Models\Reservation::where('status', 'pending')->count();
    $pendingOrders = \App\Models\Order::needsAttention()->count();
    $unread = \App\Models\ContactMessage::whereNull('read_at')->count();

    $links = [
        [
            'url' => route('admin.dashboard'),
            'active' => request()->routeIs('admin.dashboard'),
            'label' => 'Dashboard',
            'can' => 'access-admin',
        ],
        [
            'url' => route('admin.reservations.index'),
            'active' => request()->routeIs('admin.reservations.*'),
            'label' => 'Reservations',
            'can' => 'handle-guests',
            'badge' => $pending,
        ],
        [
            'url' => route('admin.messages.index'),
            'active' => request()->routeIs('admin.messages.*'),
            'label' => 'Messages',
            'can' => 'handle-guests',
            'badge' => $unread,
        ],
        [
            'url' => route('admin.orders.index'),
            'active' => request()->routeIs('admin.orders.*'),
            'label' => 'Orders',
            'can' => 'handle-guests',
            'badge' => $pendingOrders,
        ],
        [
            'url' => route('admin.items.index'),
            'active' => request()->routeIs('admin.items.*'),
            'label' => 'Menu items',
            'can' => 'manage-menu',
        ],
        [
            'url' => route('admin.categories.index'),
            'active' => request()->routeIs('admin.categories.*'),
            'label' => 'Categories',
            'can' => 'manage-menu',
        ],
        [
            'url' => route('admin.content.index', 'coupons'),
            'active' => request()->is('admin/content/coupons*'),
            'label' => 'Coupons',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.content.index', 'gallery'),
            'active' => request()->is('admin/content/gallery*'),
            'label' => 'Gallery',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.content.index', 'promotions'),
            'active' => request()->is('admin/content/promotions*'),
            'label' => 'Offers',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.content.index', 'testimonials'),
            'active' => request()->is('admin/content/testimonials*'),
            'label' => 'Testimonials',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.content.index', 'faqs'),
            'active' => request()->is('admin/content/faqs*'),
            'label' => 'FAQ',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.content.index', 'team'),
            'active' => request()->is('admin/content/team*'),
            'label' => 'Team',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.subscribers.index'),
            'active' => request()->routeIs('admin.subscribers.*'),
            'label' => 'Subscribers',
            'can' => 'manage-content',
        ],
        [
            'url' => route('admin.settings.edit'),
            'active' => request()->routeIs('admin.settings.*'),
            'label' => 'Settings',
            'can' => 'manage-settings',
        ],
        [
            'url' => route('admin.users.index'),
            'active' => request()->routeIs('admin.users.*'),
            'label' => 'Users',
            'can' => 'manage-users',
        ],
        [
            'url' => route('admin.activity.index'),
            'active' => request()->routeIs('admin.activity.*'),
            'label' => 'Activity log',
            'can' => 'view-logs',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} | Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-warm lg:flex">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">Skip to
        content</a>

    <aside class="bg-navy text-white lg:min-h-screen lg:w-64 lg:shrink-0">
        <div class="px-5 py-4">
            <p class="font-heading text-xl font-bold text-white">{{ setting('name', config('app.name')) }}</p>
            <p class="text-xs text-white/60">Admin &middot; {{ auth()->user()->name }}
                ({{ auth()->user()->role->value }})</p>
        </div>
        <nav aria-label="Admin" class="flex flex-wrap gap-1 px-3 pb-4 lg:flex-col">
            @foreach ($links as $link)
                @can($link['can'])
                    <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif
                        class="rounded-lg px-3 py-2 text-sm hover:bg-white/10 {{ $link['active'] ? 'bg-white/15 font-semibold' : '' }}">
                        {{ $link['label'] }}
                        @if (!empty($link['badge']))
                            <span
                                class="ml-1 rounded-full bg-terracotta-dark px-2 py-0.5 text-xs text-white">{{ $link['badge'] }}</span>
                        @endif
                    </a>
                @endcan
            @endforeach
            <a href="{{ route('home') }}" target="_blank" rel="noopener"
                class="rounded-lg px-3 py-2 text-sm hover:bg-white/10">View site</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-white/10">Log
                    out</button>
            </form>
        </nav>
    </aside>

    <div class="min-w-0 flex-1">
        <main id="main" class="mx-auto max-w-6xl p-4 sm:p-8">
            <h1 class="text-3xl font-bold">{{ $title }}</h1>
            {{ $slot }}
        </main>
    </div>

    <dialog id="confirm-dialog" aria-labelledby="confirm-title"
        class="m-auto w-full max-w-sm rounded-2xl bg-surface p-6 backdrop:bg-black/60">
        <h2 id="confirm-title" class="text-xl font-semibold">Are you sure?</h2>
        <p data-confirm-text class="mt-2 text-sm"></p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-confirm-no class="btn border border-navy/20 text-navy">Cancel</button>
            <button type="button" data-confirm-yes class="btn bg-red-700 text-white hover:bg-red-800">Yes,
                continue</button>
        </div>
    </dialog>

    @if (session('status'))
        <div id="flash-message" data-message="{{ session('status') }}" hidden></div>
    @elseif (session('error'))
        <div id="flash-message" data-message="{{ session('error') }}" data-type="error" hidden></div>
    @endif
</body>

</html>
