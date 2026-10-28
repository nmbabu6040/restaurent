<x-admin.layout title="Dashboard">
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="card p-5">
                <p class="text-sm">{{ $stat['label'] }}</p>
                <p class="mt-1 font-heading text-4xl font-bold text-navy">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card p-6" aria-labelledby="upcoming-title">
            <h2 id="upcoming-title" class="text-xl font-semibold">Upcoming reservations</h2>
            @forelse ($upcoming as $r)
                <div class="mt-3 flex justify-between gap-3 border-t border-navy/10 pt-3 text-sm">
                    <div>
                        <p class="font-semibold text-navy">{{ $r->name }} &middot; {{ $r->party_size }} guests
                        </p>
                        <p>{{ $r->reserved_at->format('D, d M Y, g:i A') }}</p>
                    </div>
                    <span
                        class="self-start rounded-full bg-navy/10 px-3 py-1 text-xs font-semibold">{{ ucfirst($r->status->value) }}</span>
                </div>
            @empty
                <p class="mt-3 text-sm">No upcoming reservations.</p>
            @endforelse
        </section>

        <section class="card p-6" aria-labelledby="messages-title">
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

    <section class="card mt-6 p-6" aria-labelledby="orders-title">
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
</x-admin.layout>
