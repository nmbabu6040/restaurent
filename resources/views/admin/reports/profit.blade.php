<x-admin.layout title="Profit & loss">
    @include('admin.reports._filter', [
        'action' => route('admin.profit.index'),
        'exportUrl' => route('admin.profit.export', request()->query()),
    ])

    @if ($s['uncosted'] > 0)
        <p role="status" class="mt-4 rounded-lg bg-amber-700 p-3 text-sm font-semibold text-white">
            {{ number_format($s['uncosted']) }} order {{ $s['uncosted'] === 1 ? 'line has' : 'lines have' }} no cost
            recorded (older orders, or dishes without a cost price or recipe), so food cost is understated and profit
            looks higher than it really is.
            Set a cost price or a recipe on those dishes.
        </p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.stat label="Revenue" :value="money($s['revenue'])" icon="cash" tone="indigo" />
        <x-admin.stat label="Food cost" :value="money($s['cogs'])" icon="utensils" tone="amber" />
        <x-admin.stat label="Gross profit" :value="money($s['gross'])" icon="chart" tone="cyan" :sub="$s['gross_margin'] . '% margin'" />
        <x-admin.stat label="Wastage, riders and expenses" :value="money($s['wastage'] + $s['courier'] + $s['expenses'])" icon="tag" tone="violet"
            :sub="'Riders ' . money($s['courier']) . ', wastage ' . money($s['wastage'])" />
        <x-admin.stat label="Net profit" :value="money($s['net'])" icon="trend" :tone="$s['net'] >= 0 ? 'emerald' : 'rose'" :sub="$s['net_margin'] . '% margin'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-admin.chart title="Revenue and costs" :config="$charts['costs']" />
        <x-admin.chart title="Profit" :config="$charts['profit']" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card p-6" aria-labelledby="statement-title">
            <h2 id="statement-title" class="text-xl font-semibold">Profit and loss statement</h2>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 text-left font-normal">Revenue (cash and paid online orders, with
                            delivery fees, after discounts)</th>
                        <td class="py-2 text-right">{{ money($s['revenue']) }}</td>
                    </tr>
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 text-left font-normal">&minus; Food cost</th>
                        <td class="py-2 text-right">{{ money($s['cogs']) }}</td>
                    </tr>
                    <tr class="border-b border-navy/10 font-semibold text-navy">
                        <th scope="row" class="py-2 text-left">Gross profit</th>
                        <td class="py-2 text-right">{{ money($s['gross']) }}</td>
                    </tr>
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 text-left font-normal">&minus; Stock wastage</th>
                        <td class="py-2 text-right">{{ money($s['wastage']) }}</td>
                    </tr>
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 text-left font-normal">&minus; Rider delivery fees</th>
                        <td class="py-2 text-right">{{ money($s['courier']) }}</td>
                    </tr>
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 text-left font-normal">&minus; Operating expenses</th>
                        <td class="py-2 text-right">{{ money($s['expenses']) }}</td>
                    </tr>
                    @foreach ($expenseCategories as $key => $amount)
                        <tr class="border-b border-navy/5 text-xs">
                            <th scope="row" class="py-1 pl-4 text-left font-normal">
                                {{ $categoryNames[$key] ?? $key }}</th>
                            <td class="py-1 text-right">{{ money($amount) }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold {{ $s['net'] >= 0 ? 'text-green-800' : 'text-red-700' }}">
                        <th scope="row" class="py-3 text-left">Net profit</th>
                        <td class="py-3 text-right">{{ money($s['net']) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <x-admin.chart title="Expenses by category" :config="$charts['expenses']" />
    </div>

    <section class="card mt-8 overflow-x-auto p-6" aria-labelledby="dish-title">
        <h2 id="dish-title" class="text-xl font-semibold">Profit by dish</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">Dish</th>
                    <th scope="col" class="py-2 text-right">Sold</th>
                    <th scope="col" class="py-2 text-right">Revenue</th>
                    <th scope="col" class="py-2 text-right">Cost</th>
                    <th scope="col" class="py-2 text-right">Profit</th>
                    <th scope="col" class="py-2 text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dishes as $d)
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 font-normal">{{ $d['name'] }}</th>
                        <td class="py-2 text-right">{{ $d['qty'] }}</td>
                        <td class="py-2 text-right">{{ money($d['revenue']) }}</td>
                        <td class="py-2 text-right">{{ $d['costed'] ? money($d['cost']) : 'no cost set' }}</td>
                        <td class="py-2 text-right">{{ money($d['profit']) }}</td>
                        <td class="py-2 text-right">
                            {{ $d['costed'] && $d['margin'] !== null ? $d['margin'] . '%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-4 text-center">No sales in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs">Dish figures are before order-level discounts and delivery fees. Food cost is the cost
            saved on each order when it was placed.</p>
    </section>
</x-admin.layout>
