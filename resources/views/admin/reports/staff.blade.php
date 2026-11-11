<x-admin.layout title="Staff sales">
    @include('admin.reports._filter', [
        'action' => route('admin.staff.index'),
        'exportUrl' => route('admin.staff.export', request()->query()),
    ])

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Restaurant sales" :value="money($totals['revenue'])" icon="cash" tone="indigo" />
        <x-admin.stat label="Bills" :value="number_format($totals['bills'])" icon="receipt" tone="emerald" />
        <x-admin.stat label="Discounts given" :value="money($totals['discount'])" icon="tag" tone="rose" />
        <x-admin.stat label="Cancelled bills" :value="number_format($totals['cancelled'])" icon="clock" tone="amber" />
    </div>

    <div class="mt-6"><x-admin.chart title="Sales by staff member" :config="$chart" /></div>

    <section class="card mt-6 overflow-x-auto p-6" aria-labelledby="staff-title">
        <h2 id="staff-title" class="text-xl font-semibold">By staff member</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">Staff</th>
                    <th scope="col" class="py-2 text-right">Bills</th>
                    <th scope="col" class="py-2 text-right">Sales</th>
                    <th scope="col" class="py-2 text-right">Average bill</th>
                    <th scope="col" class="py-2 text-right">Items</th>
                    <th scope="col" class="py-2 text-right">Discounts</th>
                    <th scope="col" class="py-2 text-right">Cancelled</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b border-navy/5">
                        <th scope="row" class="py-2 font-normal">{{ $r['name'] }}</th>
                        <td class="py-2 text-right">{{ $r['bills'] }}</td>
                        <td class="py-2 text-right">{{ money($r['revenue']) }}</td>
                        <td class="py-2 text-right">{{ money($r['average']) }}</td>
                        <td class="py-2 text-right">{{ $r['items'] }}</td>
                        <td class="py-2 text-right">{{ money($r['discount']) }}</td>
                        <td class="py-2 text-right">{{ $r['cancelled'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-4 text-center">No restaurant bills in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs">Only bills taken at the counter (POS). Online orders are not included. A high discount
            or cancel count is worth a conversation, not always a problem.</p>
    </section>
</x-admin.layout>
