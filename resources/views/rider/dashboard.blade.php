<x-rider.layout title="My deliveries" :signature="$signature">
    <div class="mt-4 grid grid-cols-2 gap-3">
        <x-admin.stat label="Delivered today" :value="number_format($deliveredToday)" icon="receipt" tone="emerald" />
        <x-admin.stat label="To deliver" :value="number_format($open->count())" icon="truck" tone="indigo" />
        <x-admin.stat label="Cash to hand in" :value="money($cashToHandIn)" icon="cash" tone="amber" />
        <x-admin.stat label="Fees earned" :value="money($feesEarned)" icon="chart" tone="cyan" sub="not yet settled" />
    </div>

    @unless ($rider->is_online)
        <p class="mt-4 rounded-lg bg-amber-700 p-3 text-sm font-semibold text-white">You are offline. Tap "Offline" at the
            top to go online so the office can give you deliveries.</p>
    @endunless

    <section class="mt-6" aria-labelledby="open-title">
        <h2 id="open-title" class="text-xl font-semibold">Active</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($open as $d)
                <li>
                    <a href="{{ route('rider.deliveries.show', $d) }}" class="card block p-4 hover:shadow-lg">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-navy">{{ $d->order->reference }} &middot; {{ $d->order->name }}
                            </p>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->status->badge() }}">{{ $d->status->label() }}</span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm">{{ $d->order->address }}</p>
                        <p class="mt-2 text-sm font-semibold">
                            @if ($d->order->payment_method === 'cod')
                                Collect {{ money($d->order->total) }} in cash
                            @else
                                Paid online: no cash
                            @endif
                        </p>
                    </a>
                </li>
            @empty
                <li class="card p-6 text-center text-sm">Nothing to deliver right now. This page updates by itself.</li>
            @endforelse
        </ul>
    </section>

    @if ($problems->isNotEmpty())
        <section class="mt-6" aria-labelledby="problems-title">
            <h2 id="problems-title" class="text-xl font-semibold">Problems you reported</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($problems as $d)
                    <li class="card p-3"><span class="font-semibold text-navy">{{ $d->order->reference }}</span>:
                        {{ $d->failed_reason }} <span class="text-xs">(waiting for the office)</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-rider.layout>
