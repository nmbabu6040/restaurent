<x-admin.layout title="Downloads">
    <p class="mt-2 text-sm">Download your data as a spreadsheet (<strong>CSV</strong>, opens in Excel and shows Bangla
        correctly) or a printable <strong>PDF</strong>. PDF is English only and shows the first 1,500 rows. These files
        contain customers' personal details, so keep them safe.</p>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @foreach ($datasets as $key => $dataset)
            <form method="GET" action="{{ route('admin.downloads.get', $key) }}" class="card p-5">
                <h2 class="text-lg font-semibold">{{ $dataset['label'] }}</h2>
                @if ($dataset['dated'])
                    <div class="mt-3 flex flex-wrap gap-3">
                        <div>
                            <label for="from-{{ $key }}" class="block text-xs font-medium text-navy">From
                                (optional)</label>
                            <input id="from-{{ $key }}" type="date" name="from"
                                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="to-{{ $key }}" class="block text-xs font-medium text-navy">To
                                (optional)</label>
                            <input id="to-{{ $key }}" type="date" name="to"
                                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-1.5 text-sm">
                        </div>
                    </div>
                @else
                    <p class="mt-2 text-sm">Everything, as it is now.</p>
                @endif
                <div class="mt-4 flex gap-3">
                    <button type="submit" name="format" value="csv" class="btn-primary !py-2">CSV</button>
                    <button type="submit" name="format" value="pdf"
                        class="btn border border-navy/30 !py-2 text-navy">PDF</button>
                </div>
            </form>
        @endforeach
    </div>

    <p class="mt-6 text-sm">Reports have their own downloads: <a href="{{ route('admin.sales.index') }}"
            class="font-semibold text-navy underline">Sales</a>, <a href="{{ route('admin.profit.index') }}"
            class="font-semibold text-navy underline">Profit &amp; loss</a>. A bill or challan for one order is on that
        order's page.</p>
</x-admin.layout>
