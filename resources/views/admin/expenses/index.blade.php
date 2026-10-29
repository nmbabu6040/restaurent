<x-admin.layout title="Expenses">
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat :label="'Total, ' . $month->format('F Y')" :value="money($total)" icon="cash" tone="rose" />
        @foreach (collect($byCategory)->sortDesc()->take(2) as $key => $amount)
            <x-admin.stat :label="$categories[$key] ?? $key" :value="money($amount)" icon="tag" :tone="$loop->first ? 'amber' : 'violet'" />
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.expenses.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="month" class="block text-sm font-medium text-navy">Month</label>
                <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="category" class="block text-sm font-medium text-navy">Category</label>
                <select id="category" name="category"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary !py-2">Show</button>
        </form>
        <a href="{{ route('admin.expenses.create') }}" class="btn-accent">Add expense</a>
    </div>

    <p class="mt-3 text-sm">Do not enter ingredient purchases here. Use <strong>Receive stock</strong> on the
        ingredient, so food cost is not counted twice.</p>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Date</th>
                    <th scope="col" class="p-4">Category</th>
                    <th scope="col" class="p-4">Description</th>
                    <th scope="col" class="p-4 text-right">Amount</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $e)
                    <tr class="border-b border-navy/5">
                        <td class="p-4">{{ $e->expense_date->format('d M Y') }}</td>
                        <td class="p-4">{{ $categories[$e->category] ?? $e->category }}</td>
                        <td class="p-4">{{ $e->description ?: '—' }}@if ($e->supplier)
                                <br><span class="text-xs">{{ $e->supplier->name }}</span>
                            @endif
                        </td>
                        <td class="p-4 text-right font-semibold text-navy">{{ money($e->amount) }}</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.expenses.edit', $e) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                                <form method="POST" action="{{ route('admin.expenses.destroy', $e) }}"
                                    data-confirm="Delete this expense?">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="font-semibold text-red-700 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center">No expenses in {{ $month->format('F Y') }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $expenses->links() }}</div>
    </x-admin.layout>
