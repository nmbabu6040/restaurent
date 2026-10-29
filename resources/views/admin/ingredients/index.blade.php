<x-admin.layout title="Ingredients & stock">
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat label="Ingredients" :value="number_format($total)" icon="utensils" tone="indigo" />
        <x-admin.stat label="Low stock" :value="number_format($lowCount)" icon="clock" :tone="$lowCount ? 'rose' : 'emerald'" :href="route('admin.ingredients.index', ['low' => 1])" />
        <x-admin.stat label="Stock value (at cost)" :value="money($stockValue)" icon="cash" tone="amber" />
    </div>

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.ingredients.index') }}" role="search"
            class="flex flex-wrap items-end gap-3">
            <div>
                <label for="q" class="block text-sm font-medium text-navy">Search</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" name="low" value="1" class="accent-navy" @checked(request()->boolean('low'))>
                Low stock only
            </label>
            <button type="submit" class="btn-primary !py-2">Filter</button>
        </form>
        <a href="{{ route('admin.ingredients.create') }}" class="btn-accent">Add ingredient</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Ingredient</th>
                    <th scope="col" class="p-4">In stock</th>
                    <th scope="col" class="p-4">Reorder at</th>
                    <th scope="col" class="p-4">Cost per unit</th>
                    <th scope="col" class="p-4">Value</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ingredients as $i)
                    <tr class="border-b border-navy/5">
                        <td class="p-4">
                            <span class="font-semibold text-navy">{{ $i->name }}</span>
                            @unless ($i->is_active)
                                <span class="ml-1 text-xs">(inactive)</span>
                            @endunless
                            @if ($i->supplier)
                                <br><span class="text-xs">{{ $i->supplier->name }}</span>
                            @endif
                        </td>
                        <td class="p-4">
                            {{ qty($i->stock) }} {{ $i->unit }}
                            @if ($i->isLow())
                                <span
                                    class="ml-1 rounded-full bg-red-700 px-2 py-0.5 text-xs font-semibold text-white">Low</span>
                            @endif
                        </td>
                        <td class="p-4">{{ qty($i->reorder_level) }} {{ $i->unit }}</td>
                        <td class="p-4">{{ config('restaurant.currency_symbol') }} {{ qty($i->unit_cost) }} /
                            {{ $i->unit }}</td>
                        <td class="p-4">{{ money(max(0, (float) $i->stock) * (float) $i->unit_cost) }}</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.ingredients.show', $i) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Stock</a>
                                <a href="{{ route('admin.ingredients.edit', $i) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center">No ingredients yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $ingredients->links() }}</div>
</x-admin.layout>
