<x-admin.layout title="Riders">
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Riders online" :value="number_format($online)" icon="truck" tone="emerald" />
        <x-admin.stat label="Delivered today" :value="number_format($today)" icon="receipt" tone="indigo" />
        <x-admin.stat label="Cash with riders (unsettled)" :value="money($cashOut)" icon="cash" tone="amber" />
        <x-admin.stat label="Delivery problems" :value="number_format($failed)" icon="clock" :tone="$failed ? 'rose' : 'teal'" :href="$failed ? route('admin.orders.index', ['needs_rider' => 1]) : null" />
    </div>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('admin.riders.create') }}" class="btn-accent">Add rider</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Rider</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4">Fee</th>
                    <th scope="col" class="p-4">Open</th>
                    <th scope="col" class="p-4">Unsettled cash</th>
                    <th scope="col" class="p-4">Unsettled fees</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riders as $r)
                    <tr class="border-b border-navy/5">
                        <td class="p-4"><span class="font-semibold text-navy">{{ $r->name }}</span><br><span
                                class="text-xs">{{ $r->phone }} &middot; {{ $r->vehicleLabel() }}</span></td>
                        <td class="p-4">
                            @if (!$r->is_active)
                                Inactive
                            @elseif ($r->is_online)
                                <span class="font-semibold text-green-800">Online</span>
                            @else
                                Offline
                            @endif
                            @if ($r->failed_count)
                                <span
                                    class="ml-1 rounded-full bg-red-700 px-2 py-0.5 text-xs font-semibold text-white">{{ $r->failed_count }}
                                    problem</span>
                            @endif
                        </td>
                        <td class="p-4">{{ money($r->fee_per_delivery) }}</td>
                        <td class="p-4">{{ $r->open_count }}</td>
                        <td class="p-4">{{ money($r->unsettled_cash ?? 0) }}</td>
                        <td class="p-4">{{ money($r->unsettled_fees ?? 0) }}</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.riders.show', $r) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Open</a>
                                <a href="{{ route('admin.riders.edit', $r) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center">No riders yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $riders->links() }}</div>
</x-admin.layout>
