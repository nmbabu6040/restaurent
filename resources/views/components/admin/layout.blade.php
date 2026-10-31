@props(['title' => 'Dashboard'])

@php
    $user = auth()->user();
    $theme = request()->cookie('admin_theme') === 'dark' ? 'dark' : 'light';
    $maintenance = setting('maintenance_enabled') === '1';

    $badge = [
        'orders' => \App\Models\Order::needsAttention()->count(),
        'reservations' => \App\Models\Reservation::where('status', 'pending')->count(),
        'messages' => \App\Models\ContactMessage::whereNull('read_at')->count(),
        'reviews' => $user->can('handle-guests')
            ? \App\Models\Review::where('status', 'pending')->count() +
                \App\Models\ReviewReply::where('status', 'pending')->count()
            : 0,
        'riders' => $user->can('manage-riders') ? \App\Models\Delivery::where('status', 'failed')->count() : 0,
        'stock' => $user->can('manage-inventory') ? \App\Models\Ingredient::low()->count() : 0,
    ];

    $c = fn(string $res) => route('admin.content.index', $res);
    $is = fn(string $pattern) => request()->routeIs($pattern);
    $isContent = fn(string $res) => request()->is('admin/content/' . $res . '*');

    $groups = [
        'Operations' => [
            ['Dashboard', 'grid', route('admin.dashboard'), $is('admin.dashboard'), 'access-admin'],
            ['Orders', 'bag', route('admin.orders.index'), $is('admin.orders.*'), 'handle-guests', $badge['orders']],
            [
                'Reservations',
                'calendar',
                route('admin.reservations.index'),
                $is('admin.reservations.*'),
                'handle-guests',
                $badge['reservations'],
            ],
            [
                'Messages',
                'mail',
                route('admin.messages.index'),
                $is('admin.messages.*'),
                'handle-guests',
                $badge['messages'],
            ],
            [
                'Reviews',
                'star',
                route('admin.reviews.index'),
                $is('admin.reviews.*'),
                'handle-guests',
                $badge['reviews'],
            ],
            ['Riders', 'truck', route('admin.riders.index'), $is('admin.riders.*'), 'manage-riders', $badge['riders']],
            ['Delivery areas', 'pin', $c('zones'), $isContent('zones'), 'manage-content'],
        ],
        'Menu' => [
            ['Menu items', 'utensils', route('admin.items.index'), $is('admin.items.*'), 'manage-menu'],
            ['Categories', 'layers', route('admin.categories.index'), $is('admin.categories.*'), 'manage-menu'],
            ['Coupons', 'tag', $c('coupons'), $isContent('coupons'), 'manage-content'],
        ],
        'Website' => [
            ['Pages', 'file', route('admin.pages.index'), $is('admin.pages.*'), 'manage-content'],
            [
                'Home sections',
                'layout',
                route('admin.home-sections.index'),
                $is('admin.home-sections.*'),
                'manage-content',
            ],
            ['Home highlights', 'check', $c('usps'), $isContent('usps'), 'manage-content'],
            ['About values', 'heart', $c('values'), $isContent('values'), 'manage-content'],
            ['Gallery', 'image', $c('gallery'), $isContent('gallery'), 'manage-content'],
            ['Offers', 'percent', $c('promotions'), $isContent('promotions'), 'manage-content'],
            ['Testimonials', 'message', $c('testimonials'), $isContent('testimonials'), 'manage-content'],
            ['FAQ', 'help', $c('faqs'), $isContent('faqs'), 'manage-content'],
            ['Team', 'users', $c('team'), $isContent('team'), 'manage-content'],
            ['Subscribers', 'at', route('admin.subscribers.index'), $is('admin.subscribers.*'), 'manage-content'],
            ['QR codes', 'qr', route('admin.qr.index'), $is('admin.qr.*'), 'manage-content'],
        ],
        'Inventory & finance' => [
            [
                'Ingredients & stock',
                'package',
                route('admin.ingredients.index'),
                $is('admin.ingredients.*'),
                'manage-inventory',
                $badge['stock'],
            ],
            ['Suppliers', 'briefcase', $c('suppliers'), $isContent('suppliers'), 'manage-inventory'],
            ['Expenses', 'wallet', route('admin.expenses.index'), $is('admin.expenses.*'), 'view-reports'],
            ['Profit & loss', 'pie', route('admin.profit.index'), $is('admin.profit.*'), 'view-reports'],
        ],
        'Reports' => [
            ['Sales', 'trend', route('admin.sales.index'), $is('admin.sales.*'), 'view-reports'],
            ['Customers', 'users', route('admin.customers.index'), $is('admin.customers.*'), 'view-reports'],
            ['Downloads', 'download', route('admin.downloads.index'), $is('admin.downloads.*'), 'view-reports'],
        ],
        'System' => [
            [
                'Appearance & site',
                'palette',
                route('admin.appearance.edit'),
                $is('admin.appearance.*'),
                'manage-settings',
            ],
            ['SEO', 'search', route('admin.seo.edit'), $is('admin.seo.*'), 'manage-settings'],
            ['Settings', 'sliders', route('admin.settings.edit'), $is('admin.settings.*'), 'manage-settings'],
            ['Users', 'shield', route('admin.users.index'), $is('admin.users.*'), 'manage-users'],
            ['Backups', 'database', route('admin.backups.index'), $is('admin.backups.*'), 'manage-settings'],
            ['Activity log', 'activity', route('admin.activity.index'), $is('admin.activity.*'), 'view-logs'],
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
        <div class="px-5 pb-2 pt-4">
            <p class="font-heading text-xl font-bold">{{ setting('name', config('app.name')) }}</p>
            <p class="text-xs text-white/70">Admin &middot; {{ $user->name }} ({{ $user->role->value }})</p>
        </div>

        <div class="px-3 pb-2">
            <label for="nav-filter" class="sr-only">Find a menu</label>
            <input id="nav-filter" type="search" data-nav-filter placeholder="Find a menu…  ( / )" autocomplete="off"
                class="w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/60 focus:bg-white/20">
        </div>

        <nav aria-label="Admin" data-admin-nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
            @foreach ($groups as $group => $links)
                @php
                    $visible = collect($links)->filter(fn($l) => $user->can($l[4]));
                    $hasActive = $visible->contains(fn($l) => $l[3]);
                @endphp
                @if ($visible->isNotEmpty())
                    <details data-nav-group="{{ \Illuminate\Support\Str::slug($group) }}"
                        @if ($hasActive || $loop->first) open @endif>
                        <summary
                            class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-white/70 hover:text-white">
                            <span>{{ $group }}</span>
                            <x-admin.icon name="chevron" class="chev h-4 w-4" />
                        </summary>
                        <ul class="mt-0.5 space-y-0.5">
                            @foreach ($visible as $link)
                                @php
                                    [$label, $icon, $url, $active, $gate, $count] = $link + [5 => 0];
                                @endphp
                                <li data-nav-item="{{ strtolower($label) }}">
                                    <a href="{{ $url }}"
                                        @if ($active) aria-current="page" @endif
                                        class="group flex items-center gap-3 rounded-lg border-l-4 px-3 py-2 text-sm transition hover:bg-white/10 {{ $active ? 'border-[#E39A7B] bg-white/15 font-semibold' : 'border-transparent' }}">
                                        <x-admin.icon :name="$icon"
                                            class="h-5 w-5 shrink-0 opacity-80 transition group-hover:scale-110 group-hover:opacity-100" />
                                        <span class="flex-1">{{ $label }}</span>
                                        @if (!empty($count))
                                            <span
                                                class="rounded-full bg-terracotta-dark px-2 py-0.5 text-xs font-semibold text-white">{{ $count }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach
        </nav>

        <div class="space-y-1 border-t border-white/10 p-3">
            @can('handle-guests')
                <button type="button" data-sound-toggle aria-pressed="false"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-white/10">
                    <x-admin.icon name="bell" class="h-5 w-5 opacity-80" /><span data-sound-label>New order alert sound:
                        off</span>
                </button>
                <div data-alert-url="{{ route('admin.orders.poll') }}" hidden></div>
            @endcan
            <button type="button" data-theme-toggle
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-white/10">
                <x-admin.icon name="moon" class="h-5 w-5 opacity-80" />
                <span><span class="when-light">Switch to dark mode</span><span class="when-dark">Switch to light
                        mode</span></span>
            </button>
            <a href="{{ route('home') }}" target="_blank" rel="noopener"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-white/10"><x-admin.icon
                    name="external" class="h-5 w-5 opacity-80" /> View site</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-white/10"><x-admin.icon
                        name="logout" class="h-5 w-5 opacity-80" /> Log out</button>
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
