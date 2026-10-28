<x-admin.layout title="Orders">
    <form method="GET" action="{{ route('admin.orders.index') }}" role="search"
        class="mt-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-sm font-medium text-navy">Status</label>
            <select id="status" name="status"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (\App\Enums\OrderStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->adminLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-navy">Type</label>
            <select id="type" name="type"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="delivery" @selected(request('type') === 'delivery')>Delivery</option>
                <option value="pickup" @selected(request('type') === 'pickup')>Pickup</option>
            </select>
        </div>
        <div>
            <label for="date" class="block text-sm font-medium text-navy">Date</label>
            <input id="date" type="date" name="date" value="{{ request('date') }}"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <div>
            <label for="q" class="block text-sm font-medium text-navy">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100"
                placeholder="Number, name, phone"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn-primary !py-2">Filter</button>
        @if (request()->hasAny(['status', 'type', 'date', 'q']))
            <a href="{{ route('admin.orders.index') }}"
                class="py-2 text-sm font-semibold text-navy hover:text-terracotta-dark">Reset</a>
        @endif
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Order</th>
                    <th scope="col" class="p-4">Customer</th>
                    <th scope="col" class="p-4">Type</th>
                    <th scope="col" class="p-4">Total</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4">Placed</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $o)
                    <tr class="border-b border-navy/5">
                        <td class="p-4 font-mono text-xs">{{ $o->reference }}</td>
                        <td class="p-4"><span class="font-semibold text-navy">{{ $o->name }}</span><br><span
                                class="text-xs">{{ $o->phone }}</span></td>
                        <td class="p-4">{{ ucfirst($o->type->value) }}</td>
                        <td class="p-4">{{ money($o->total) }}<br>
                            <span class="text-xs">{{ $o->payment_method === 'cod' ? 'Cash' : 'Online' }} &middot;
                                {{ ucfirst($o->payment_status) }}</span>
                        </td>
                        <td class="p-4"><span
                                class="rounded-full px-3 py-1 text-xs font-semibold {{ $o->status->badge() }}">{{ $o->status->adminLabel() }}</span>
                        </td>
                        <td class="p-4">{{ $o->created_at->format('d M, g:i A') }}</td>
                        <td class="p-4 text-right"><a href="{{ route('admin.orders.show', $o) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
</x-admin.layout>
