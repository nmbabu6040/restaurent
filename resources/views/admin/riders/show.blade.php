<x-admin.layout :title="$rider->name">
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Unsettled deliveries" :value="number_format($unsettled->count())" icon="truck" tone="indigo" />
        <x-admin.stat label="Cash collected" :value="money($cash)" icon="cash" tone="amber" sub="from cash orders" />
        <x-admin.stat label="Rider fees earned" :value="money($fees)" icon="receipt" tone="emerald" />
        <x-admin.stat :label="$cash - $fees >= 0 ? 'Rider hands in' : 'You pay the rider'" :value="money(abs($cash - $fees))" icon="chart" :tone="$cash - $fees >= 0 ? 'cyan' : 'rose'" sub="cash minus fees" />
    </div>

    <p class="mt-3 text-sm">
        {{ $rider->phone }} &middot; {{ $rider->vehicleLabel() }}@if ($rider->vehicle_number)
            ({{ $rider->vehicle_number }})
        @endif
        &middot; {{ $rider->is_active ? ($rider->is_online ? 'Online' : 'Offline') : 'Inactive' }}
        &middot; <a href="{{ route('admin.riders.edit', $rider) }}"
            class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
        &middot; <a href="{{ route('admin.riders.index') }}"
            class="font-semibold text-navy hover:text-terracotta-dark">All riders</a>
    </p>

    <section class="card mt-6 overflow-x-auto p-6" aria-labelledby="unsettled-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="unsettled-title" class="text-xl font-semibold">Waiting to be settled</h2>
            @if ($unsettled->isNotEmpty())
                <form method="POST" action="{{ route('admin.riders.settle', $rider) }}"
                    data-confirm="Settle {{ $unsettled->count() }} deliveries with {{ $rider->name }}? Cash {{ money($cash) }}, fees {{ money($fees) }}.">
                    @csrf
                    <button type="submit" class="btn-primary !py-2">Settle all</button>
                </form>
            @endif
        </div>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">Delivered</th>
                    <th scope="col" class="py-2">Order</th>
                    <th scope="col" class="py-2 text-right">Cash collected</th>
                    <th scope="col" class="py-2 text-right">Fee</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($unsettled as $d)
                    <tr class="border-b border-navy/5">
                        <td class="py-2">{{ $d->delivered_at?->format('d M, g:i A') }}</td>
                        <td class="py-2"><a href="{{ route('admin.orders.show', $d->order_id) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">{{ $d->order?->reference }}</a>
                        </td>
                        <td class="py-2 text-right">
                            {{ (float) $d->cash_collected > 0 ? money($d->cash_collected) : 'Paid online' }}</td>
                        <td class="py-2 text-right">{{ money($d->rider_fee) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-4 text-center">Everything is settled.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs">Settling records that the cash and the fees have been squared with the rider. Rider fees
            already count as a cost in profit and loss from the day of delivery.</p>
    </section>

    <section class="card mt-6 overflow-x-auto p-6" aria-labelledby="history-title">
        <h2 id="history-title" class="text-xl font-semibold">Delivery history</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">Assigned</th>
                    <th scope="col" class="py-2">Order</th>
                    <th scope="col" class="py-2">Status</th>
                    <th scope="col" class="py-2 text-right">Fee</th>
                    <th scope="col" class="py-2">Settled</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $d)
                    <tr class="border-b border-navy/5">
                        <td class="py-2">{{ $d->assigned_at?->format('d M, g:i A') }}</td>
                        <td class="py-2"><a href="{{ route('admin.orders.show', $d->order_id) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">{{ $d->order?->reference }}</a><br><span
                                class="text-xs">{{ $d->order?->name }}</span></td>
                        <td class="py-2"><span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->status->badge() }}">{{ $d->status->label() }}</span>
                            @if ($d->failed_reason)
                                <br><span class="text-xs">{{ $d->failed_reason }}</span>
                            @endif
                        </td>
                        <td class="py-2 text-right">{{ money($d->rider_fee) }}</td>
                        <td class="py-2">{{ $d->settled_at ? $d->settled_at->format('d M Y') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center">No deliveries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $history->links() }}</div>
    </section>
</x-admin.layout>
