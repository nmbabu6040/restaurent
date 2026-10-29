@php
    $bag = fn(string $name, string $field) => $errors->getBag($name)->first($field);
    $field = 'mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta';
@endphp

<x-admin.layout :title="$ingredient->name">
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat label="In stock" :value="qty($ingredient->stock) . ' ' . $ingredient->unit" icon="utensils" :tone="$ingredient->isLow() ? 'rose' : 'emerald'" :sub="$ingredient->isLow()
            ? 'Low: reorder level ' . qty($ingredient->reorder_level)
            : 'Reorder level ' . qty($ingredient->reorder_level)" />
        <x-admin.stat label="Cost per unit" :value="config('restaurant.currency_symbol') . ' ' . qty($ingredient->unit_cost)" icon="cash" tone="amber" :sub="'per ' . $ingredient->unit" />
        <x-admin.stat label="Stock value" :value="money(max(0, (float) $ingredient->stock) * (float) $ingredient->unit_cost)" icon="chart" tone="indigo" :sub="$ingredient->supplier?->name" />
    </div>

    <p class="mt-3 text-sm">
        <a href="{{ route('admin.ingredients.edit', $ingredient) }}"
            class="font-semibold text-navy hover:text-terracotta-dark">Edit details</a> &middot;
        <a href="{{ route('admin.ingredients.index') }}" class="font-semibold text-navy hover:text-terracotta-dark">All
            ingredients</a>
        @if ($dishes->isNotEmpty())
            &middot; Used in: {{ $dishes->pluck('name_en')->implode(', ') }}
        @endif
    </p>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.ingredients.receive', $ingredient) }}" class="card space-y-4 p-5">
            @csrf
            <h2 class="text-lg font-semibold">Receive stock</h2>
            <div>
                <label for="r-qty" class="block text-sm font-medium text-navy">Quantity
                    ({{ $ingredient->unit }})</label>
                <input id="r-qty" name="quantity" type="number" step="0.001" min="0" required
                    class="{{ $field }}" value="{{ old('quantity') }}">
                <p class="mt-1 text-sm text-red-700">{{ $bag('receive', 'quantity') }}</p>
            </div>
            <div>
                <label for="r-cost" class="block text-sm font-medium text-navy">Price per {{ $ingredient->unit }}
                    (optional)</label>
                <input id="r-cost" name="unit_cost" type="number" step="0.0001" min="0"
                    class="{{ $field }}">
                <p class="mt-1 text-sm text-red-700">{{ $bag('receive', 'unit_cost') }}</p>
            </div>
            <div>
                <label for="r-note" class="block text-sm font-medium text-navy">Note (optional)</label>
                <input id="r-note" name="note" maxlength="200" class="{{ $field }}"
                    placeholder="e.g. weekly delivery">
            </div>
            <button type="submit" class="btn-primary">Add to stock</button>
        </form>

        <form method="POST" action="{{ route('admin.ingredients.waste', $ingredient) }}" class="card space-y-4 p-5">
            @csrf
            <h2 class="text-lg font-semibold">Record wastage</h2>
            <div>
                <label for="w-qty" class="block text-sm font-medium text-navy">Quantity
                    ({{ $ingredient->unit }})</label>
                <input id="w-qty" name="quantity" type="number" step="0.001" min="0" required
                    class="{{ $field }}">
                <p class="mt-1 text-sm text-red-700">{{ $bag('waste', 'quantity') }}</p>
            </div>
            <div>
                <label for="w-note" class="block text-sm font-medium text-navy">Reason (optional)</label>
                <input id="w-note" name="note" maxlength="200" class="{{ $field }}"
                    placeholder="e.g. spoiled">
            </div>
            <button type="submit" class="btn border border-red-700 text-red-700 hover:bg-red-50">Remove from
                stock</button>
            <p class="text-xs">Wastage counts as a cost in profit and loss.</p>
        </form>

        <form method="POST" action="{{ route('admin.ingredients.count', $ingredient) }}" class="card space-y-4 p-5">
            @csrf
            <h2 class="text-lg font-semibold">Stock count</h2>
            <div>
                <label for="c-qty" class="block text-sm font-medium text-navy">Counted now
                    ({{ $ingredient->unit }})</label>
                <input id="c-qty" name="counted" type="number" step="0.001" min="0" required
                    class="{{ $field }}">
                <p class="mt-1 text-sm text-red-700">{{ $bag('count', 'counted') }}</p>
            </div>
            <div>
                <label for="c-note" class="block text-sm font-medium text-navy">Note (optional)</label>
                <input id="c-note" name="note" maxlength="200" class="{{ $field }}"
                    placeholder="e.g. monthly count">
            </div>
            <button type="submit" class="btn-primary">Set stock to this</button>
        </form>
    </div>

    <section class="card mt-8 overflow-x-auto p-6" aria-labelledby="history-title">
        <h2 id="history-title" class="text-xl font-semibold">Stock history</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">When</th>
                    <th scope="col" class="py-2">Type</th>
                    <th scope="col" class="py-2 text-right">Change</th>
                    <th scope="col" class="py-2 text-right">Balance</th>
                    <th scope="col" class="py-2">Note</th>
                    <th scope="col" class="py-2">By</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr class="border-b border-navy/5">
                        <td class="py-2">{{ $m->created_at?->format('d M Y, g:i A') }}</td>
                        <td class="py-2">{{ ucfirst($m->type) }}</td>
                        <td
                            class="py-2 text-right font-semibold {{ (float) $m->quantity < 0 ? 'text-red-700' : 'text-green-800' }}">
                            {{ (float) $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }}</td>
                        <td class="py-2 text-right">{{ qty($m->balance_after) }} {{ $ingredient->unit }}</td>
                        <td class="py-2">{{ $m->note }}</td>
                        <td class="py-2">{{ $m->user?->name ?? ($m->order_id ? 'Order' : 'System') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-4 text-center">No stock movements yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $movements->links() }}</div>
    </section>
</x-admin.layout>
