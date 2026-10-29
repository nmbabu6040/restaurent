<x-admin.layout title="Customers">
    @include('admin.reports._filter', [
        'action' => route('admin.customers.index'),
        'exportUrl' => route('admin.customers.export'),
    ])

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.stat label="All customers" :value="number_format($summary['total'])" icon="users" tone="indigo" sub="ever ordered" />
        <x-admin.stat label="Active in period" :value="number_format($summary['active'])" icon="receipt" tone="emerald" :sub="$summary['orders_per_customer'] . ' orders each'" />
        <x-admin.stat label="New customers" :value="number_format($summary['new'])" icon="trend" tone="pink"
            sub="first order in period" />
        <x-admin.stat label="Returning customers" :value="number_format($summary['returning'])" icon="repeat" tone="cyan"
            sub="ordered before too" />
        <x-admin.stat label="Repeat rate" :value="number_format($summary['repeat_rate'], 1) . '%'" icon="chart" tone="amber"
            sub="ordered 2+ times, all time" />
    </div>

    <div class="mt-6">
        <x-admin.chart :title="'New and returning customers, ' . $p['group']" :config="$chart" />
    </div>

    <section class="card mt-6 overflow-x-auto p-6" aria-labelledby="top-customers-title">
        <h2 id="top-customers-title" class="text-xl font-semibold">Top customers in this period</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">Customer</th>
                    <th scope="col" class="py-2 text-right">Orders</th>
                    <th scope="col" class="py-2 text-right">Spent</th>
                    <th scope="col" class="py-2 text-right">Lifetime orders</th>
                    <th scope="col" class="py-2 text-right">Last order</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($top as $c)
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 font-normal"><span
                                class="font-semibold text-navy">{{ $c['name'] }}</span><br><span
                                class="text-xs">{{ $c['phone'] }}</span></th>
                        <td class="py-2 text-right">{{ $c['range_orders'] }}</td>
                        <td class="py-2 text-right">{{ money($c['range_spent']) }}</td>
                        <td class="py-2 text-right">{{ $c['orders'] }}</td>
                        <td class="py-2 text-right">{{ $c['last']->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center">No customers in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <p class="mt-6 text-xs">Customers are told apart by phone number (the last 9 digits, so 01712345678 and
        +8801712345678 are the same person). The CSV export lists every customer, not just this period.</p>
</x-admin.layout>
