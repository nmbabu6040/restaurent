@props(['title' => 'Dashboard'])

@php
    $theme = request()->cookie('admin_theme') === 'dark' ? 'dark' : 'light';
    $pending = \App\Models\Reservation::where('status', 'pending')->count();
    $unread = \App\Models\ContactMessage::whereNull('read_at')->count();
    $pendingOrders = \App\Models\Order::needsAttention()->count();
    $maintenance = setting('maintenance_enabled') === '1';

    $groups = [
        'Operations' => [
            [
                'url' => route('admin.dashboard'),
                'active' => request()->routeIs('admin.dashboard'),
                'label' => 'Dashboard',
                'can' => 'access-admin',
            ],
            [
                'url' => route('admin.orders.index'),
                'active' => request()->routeIs('admin.orders.*'),
                'label' => 'Orders',
                'can' => 'handle-guests',
                'badge' => $pendingOrders,
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
        ],
        'Reports' => [
            [
                'url' => route('admin.sales.index'),
                'active' => request()->routeIs('admin.sales.*'),
                'label' => 'Sales',
                'can' => 'view-reports',
            ],
            [
                'url' => route('admin.customers.index'),
                'active' => request()->routeIs('admin.customers.*'),
                'label' => 'Customers',
                'can' => 'view-reports',
            ],
        ],
        'Menu' => [
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
        ],
        'Website' => [
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
        ],
        'System' => [
            [
                'url' => route('admin.appearance.edit'),
                'active' => request()->routeIs('admin.appearance.*'),
                'label' => 'Appearance & site',
                'can' => 'manage-settings',
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
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    @if ($favicon = setting_image('favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <title>{{ $title }} | Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-dvh overflow-hidden bg-warm lg:flex">
    <a href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3 focus:text-navy">Skip to
        content</a>

    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-black/50 lg:hidden"></div>

    <aside id="admin-sidebar" data-open="false"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#172B4D] text-white transition-transform data-[open=true]:translate-x-0 lg:static lg:translate-x-0">
        <div class="px-5 py-4">
            <p class="font-heading text-xl font-bold">{{ setting('name', config('app.name')) }}</p>
            <p class="text-xs text-white/70">Admin &middot; {{ auth()->user()->name }}
                ({{ auth()->user()->role->value }})</p>
        </div>

        <nav aria-label="Admin" class="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
            @foreach ($groups as $group => $links)
                @php $visible = collect($links)->filter(fn ($l) => auth()->user()->can($l['can'])); @endphp
                @if ($visible->isNotEmpty())
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-white/60">{{ $group }}
                        </p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($visible as $link)
                                <li>
                                    <a href="{{ $link['url'] }}"
                                        @if ($link['active']) aria-current="page" @endif
                                        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-white/10 {{ $link['active'] ? 'bg-white/15 font-semibold' : '' }}">
                                        {{ $link['label'] }}
                                        @if (!empty($link['badge']))
                                            <span
                                                class="rounded-full bg-terracotta-dark px-2 py-0.5 text-xs text-white">{{ $link['badge'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="space-y-1 border-t border-white/10 p-3">
            <button type="button" data-theme-toggle
                class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-white/10">
                <span class="when-light">Switch to dark mode</span><span class="when-dark">Switch to light mode</span>
            </button>
            <a href="{{ route('home') }}" target="_blank" rel="noopener"
                class="block rounded-lg px-3 py-2 text-sm hover:bg-white/10">View site</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-white/10">Log
                    out</button>
            </form>
        </div>
    </aside>

    <div class="flex h-dvh min-w-0 flex-1 flex-col">
        <div class="flex items-center gap-3 border-b border-navy/10 bg-surface px-4 py-3 lg:hidden">
            <button type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false"
                aria-label="Open menu" class="rounded p-1 text-navy">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="font-heading text-lg text-navy">{{ $title }}</span>
        </div>

        <main id="main" tabindex="-1" class="flex-1 overflow-y-auto focus:outline-none">
            <div class="mx-auto max-w-6xl p-4 sm:p-8">
                <h1 class="text-3xl font-bold">{{ $title }}</h1>

                @if ($maintenance)
                    <p role="status" class="mt-4 rounded-lg bg-red-700 p-3 text-sm font-semibold text-white">
                        Maintenance mode is ON: visitors see the "back soon" page.
                        @can('manage-settings')
                            <a href="{{ route('admin.appearance.edit') }}" class="underline">Switch it off</a>
                        @endcan
                    </p>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>

    <dialog id="confirm-dialog" aria-labelledby="confirm-title"
        class="m-auto w-full max-w-sm rounded-2xl bg-surface p-6 text-charcoal backdrop:bg-black/60">
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
