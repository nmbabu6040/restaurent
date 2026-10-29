<x-admin.layout title="Sales">
    @include('admin.reports._filter', [
        'action' => route('admin.sales.index'),
        'exportUrl' => route('admin.sales.export', request()->query()),
    ])

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-admin.stat label="Revenue" :value="money($summary['revenue'])" icon="cash" tone="indigo" :change="$change['revenue']"
            sub="vs previous period" />
        <x-admin.stat label="Orders" :value="number_format($summary['orders'])" icon="receipt" tone="emerald" :change="$change['orders']"
            sub="vs previous period" />
        <x-admin.stat label="Average order value" :value="money($summary['aov'])" icon="chart" tone="cyan" :change="$change['aov']"
            sub="vs previous period" />
        <x-admin.stat label="Portions sold" :value="number_format($summary['items'])" icon="utensils" tone="amber" />
        <x-admin.stat label="Delivery fees collected" :value="money($summary['delivery'])" icon="truck" tone="violet" />
        <x-admin.stat label="Discounts given" :value="money($summary['discount'])" icon="tag" tone="rose" :sub="number_format($summary['coupons']) . ' orders used a coupon'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-admin.chart title="Revenue" :config="$charts['revenue']" />
        <x-admin.chart title="Orders" :config="$charts['orders']" />
        <x-admin.chart title="Orders by type" :config="$charts['type']" />
        <x-admin.chart title="Revenue by payment method" :config="$charts['payment']" />
        <x-admin.chart title="Top dishes (portions sold)" :config="$charts['top']" />
        <x-admin.chart title="Busiest hours" :config="$charts['hours']" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card overflow-x-auto p-6" aria-labelledby="period-title">
            <h2 id="period-title" class="text-xl font-semibold">By
                {{ $p['group'] === 'daily' ? 'day' : ($p['group'] === 'weekly' ? 'week (starting Monday)' : 'month') }}
            </h2>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="border-b border-navy/10 text-navy">
                    <tr>
                        <th scope="col" class="py-2">Period</th>
                        <th scope="col" class="py-2 text-right">Orders</th>
                        <th scope="col" class="py-2 text-right">Revenue</th>
                        <th scope="col" class="py-2 text-right">Avg order</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($series as $row)
                        <tr class="border-b border-navy/5">
                            <th scope="row" class="py-2 font-normal">{{ $row['label'] }}</th>
                            <td class="py-2 text-right">{{ $row['orders'] }}</td>
                            <td class="py-2 text-right">{{ money($row['revenue']) }}</td>
                            <td class="py-2 text-right">
                                {{ $row['orders'] ? money($row['revenue'] / $row['orders']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="card overflow-x-auto p-6" aria-labelledby="top-title">
            <h2 id="top-title" class="text-xl font-semibold">Best sellers</h2>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="border-b border-navy/10 text-navy">
                    <tr>
                        <th scope="col" class="py-2">Dish</th>
                        <th scope="col" class="py-2 text-right">Sold</th>
                        <th scope="col" class="py-2 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topItems as $item)
                        <tr class="border-b border-navy/5">
                            <th scope="row" class="py-2 font-normal">{{ $item['name'] }}</th>
                            <td class="py-2 text-right">{{ $item['qty'] }}</td>
                            <td class="py-2 text-right">{{ money($item['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 text-center">No sales in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <p class="mt-6 text-xs">Sales count cash orders and paid online orders that were not cancelled, on the day the order
        was placed. Revenue includes delivery fees and is after discounts.</p>
</x-admin.layout>
