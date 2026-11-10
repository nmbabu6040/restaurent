<x-admin.layout title="Cash drawer">
    <p class="mt-1 text-sm"><a href="{{ route('admin.pos.index') }}"
            class="font-semibold text-navy hover:text-terracotta-dark">&larr; POS</a></p>

    @if ($shift)
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <x-admin.stat label="Opening cash" :value="money($shift->opening_cash)" icon="cash" tone="indigo" :sub="'since ' . $shift->opened_at->format('d M, g:i A')" />
            <x-admin.stat label="Cash taken this shift" :value="money($cashSales)" icon="receipt" tone="emerald"
                sub="restaurant bills paid in cash" />
            <x-admin.stat label="Should be in the drawer" :value="money($expected)" icon="chart" tone="amber" />
        </div>

        <form method="POST" action="{{ route('admin.pos.shift.close') }}"
            data-confirm="Close the shift? You cannot undo this." class="card mt-6 space-y-4 p-6">
            @csrf
            <h2 class="text-xl font-semibold">Close the shift</h2>
            <p class="text-sm">Count the cash in the drawer and type it here. The difference is saved for the owner.</p>
            <x-form.field name="counted_cash" type="number" label="Cash counted in the drawer" :required="true"
                step="0.01" min="0" />
            <x-form.field name="note" label="Note (optional)" maxlength="255" />
            <button type="submit" class="btn-primary">Close shift</button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.pos.shift.open') }}" class="card mt-4 space-y-4 p-6">
            @csrf
            <h2 class="text-xl font-semibold">Open a shift</h2>
            <p class="text-sm">Type the cash already in the drawer (the float). Cash payments you take after this are
                counted against it.</p>
            <x-form.field name="opening_cash" type="number" label="Opening cash" :required="true" step="0.01"
                min="0" value="0" />
            <button type="submit" class="btn-accent">Open shift</button>
        </form>
    @endif

    <section class="card mt-6 overflow-x-auto p-6" aria-labelledby="history-title">
        <h2 id="history-title" class="text-xl font-semibold">Closed shifts</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="py-2">Opened</th>
                    <th scope="col" class="py-2">Closed by</th>
                    <th scope="col" class="py-2 text-right">Expected</th>
                    <th scope="col" class="py-2 text-right">Counted</th>
                    <th scope="col" class="py-2 text-right">Difference</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $s)
                    @php $diff = (float) $s->counted_cash - (float) $s->expected_cash; @endphp
                    <tr class="border-b border-navy/5">
                        <td class="py-2">{{ $s->opened_at->format('d M, g:i A') }} to
                            {{ $s->closed_at->format('g:i A') }}</td>
                        <td class="py-2">{{ $s->closer?->name }}</td>
                        <td class="py-2 text-right">{{ money($s->expected_cash) }}</td>
                        <td class="py-2 text-right">{{ money($s->counted_cash) }}</td>
                        <td
                            class="py-2 text-right font-semibold {{ abs($diff) < 0.005 ? 'text-green-800' : 'text-red-700' }}">
                            {{ abs($diff) < 0.005 ? 'Matches' : money($diff) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center">No closed shifts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs">Only cash taken at the counter is counted. Cash that riders collect on deliveries is
            settled with the rider.</p>
    </section>
</x-admin.layout>
