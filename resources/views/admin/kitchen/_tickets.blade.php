@forelse ($tickets as $ticket)
    @php
        $order = $ticket['order'];
        $minutes = $ticket['minutes'];
        $tone =
            $minutes >= 20
                ? 'border-red-500 bg-red-950'
                : ($minutes >= 10
                    ? 'border-amber-500 bg-amber-950'
                    : 'border-emerald-500 bg-slate-900');
        $label =
            $order->source === 'pos' ? ($order->table_name ?: 'Takeaway') : $order->type->adminLabel() . ' (online)';
    @endphp
    <article data-ticket="{{ $order->id }}" class="rounded-2xl border-4 {{ $tone }} p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="font-heading text-2xl font-bold text-white">{{ $label }}</p>
                <p class="text-xs text-slate-300">{{ $order->reference }} &middot; {{ $order->name }}</p>
            </div>
            <p class="text-right text-lg font-bold">{{ $minutes }} min</p>
        </div>

        <ul class="mt-3 space-y-2">
            @foreach ($ticket['lines'] as $row)
                <li class="flex items-start justify-between gap-2 rounded-lg bg-white/10 p-2">
                    <div>
                        <p class="text-xl font-bold">{{ $row['qty'] }} &times; {{ $row['line']->name_en }}</p>
                        @if ($row['line']->optionsLabel('en'))
                            <p class="text-sm text-slate-300">{{ $row['line']->optionsLabel('en') }}</p>
                        @endif
                    </div>
                    <button type="button" data-kitchen-done
                        data-url="{{ route('admin.kitchen.lines.ready', [$order, $row['line']]) }}"
                        aria-label="{{ $row['line']->name_en }} is done"
                        class="shrink-0 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-600">Done</button>
                </li>
            @endforeach
        </ul>

        @if ($order->notes)
            <p class="mt-3 rounded-lg bg-amber-300 p-2 text-sm font-bold text-black">NOTE: {{ $order->notes }}</p>
        @endif

        <button type="button" data-kitchen-done data-url="{{ route('admin.kitchen.ready', $order) }}"
            class="mt-3 w-full rounded-xl bg-emerald-700 py-3 text-lg font-bold text-white hover:bg-emerald-600">All
            ready</button>
    </article>
@empty
    <p class="col-span-full py-24 text-center text-2xl text-slate-300">Nothing waiting. All caught up.</p>
@endforelse
