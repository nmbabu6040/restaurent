<x-admin.layout title="Dashboard">
    @if ($canReport)
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($kpis as $kpi)
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :tone="$kpi['tone']" :change="$kpi['change']"
                    :sub="$kpi['sub']" />
            @endforeach
        </div>
    @endif

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($stats as $stat)
            <x-admin.stat :label="$stat['label']" :value="number_format($stat['value'])" :icon="$stat['icon']" :tone="$stat['tone']" :href="$stat['href']" />
        @endforeach
    </div>

    @if ($canReport)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2"><x-admin.chart title="Revenue, last 30 days" :config="$revenueChart" /></div>
            <x-admin.chart title="Orders by type, 30 days" :config="$typeChart" />
            <x-admin.chart title="Top dishes, 30 days" :config="$topChart" />
            <x-admin.chart title="Order status, 30 days" :config="$statusChart" />
            <x-admin.chart title="Busiest hours, 30 days" :config="$hoursChart" />
            <div class="lg:col-span-3"><x-admin.chart title="Reservations, next 7 days" :config="$reservationChart"
                    height="h-64" /></div>
        </div>
        <p class="mt-3 text-sm">
            <a href="{{ route('admin.sales.index') }}" class="font-semibold text-navy hover:text-terracotta-dark">Open
                the full sales report &rarr;</a>
            &middot;
            <a href="{{ route('admin.customers.index') }}"
                class="font-semibold text-navy hover:text-terracotta-dark">Customer analysis &rarr;</a>
        </p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card p-6" aria-labelledby="orders-title">
            <h2 id="orders-title" class="text-xl font-semibold">Recent orders</h2>
            @forelse ($recentOrders as $o)
                <a href="{{ route('admin.orders.show', $o) }}"
                    class="mt-3 flex flex-wrap justify-between gap-3 border-t border-navy/10 pt-3 text-sm hover:text-terracotta-dark">
                    <span><span class="font-semibold text-navy">{{ $o->reference }}</span> &middot; {{ $o->name }}
                        &middot; {{ $o->type->value }}</span>
                    <span>{{ money($o->total) }} <span
                            class="ml-2 rounded-full px-2 py-0.5 text-xs font-semibold {{ $o->status->badge() }}">{{ $o->status->adminLabel() }}</span></span>
                </a>
            @empty
                <p class="mt-3 text-sm">No orders yet.</p>
            @endforelse
        </section>

        <section class="card p-6" aria-labelledby="upcoming-title">
            <h2 id="upcoming-title" class="text-xl font-semibold">Upcoming reservations</h2>
            @forelse ($upcoming as $r)
                <div class="mt-3 flex justify-between gap-3 border-t border-navy/10 pt-3 text-sm">
                    <div>
                        <p class="font-semibold text-navy">{{ $r->name }} &middot; {{ $r->party_size }} guests</p>
                        <p>{{ $r->reserved_at->format('D, d M Y, g:i A') }}</p>
                    </div>
                    <span
                        class="self-start rounded-full bg-navy/10 px-3 py-1 text-xs font-semibold">{{ ucfirst($r->status->value) }}</span>
                </div>
            @empty
                <p class="mt-3 text-sm">No upcoming reservations.</p>
            @endforelse
        </section>

        <section class="card p-6 lg:col-span-2" aria-labelledby="messages-title">
            <h2 id="messages-title" class="text-xl font-semibold">Latest messages</h2>
            @forelse ($messages as $m)
                <div class="mt-3 border-t border-navy/10 pt-3 text-sm">
                    <p class="font-semibold text-navy">{{ $m->name }} <span class="font-normal">&middot;
                            {{ $m->created_at->diffForHumans() }}</span></p>
                    <p class="line-clamp-2">{{ $m->message }}</p>
                </div>
            @empty
                <p class="mt-3 text-sm">No messages yet.</p>
            @endforelse
        </section>
    </div>
</x-admin.layout>
