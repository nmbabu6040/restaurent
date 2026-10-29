@php
    $order = $delivery->order;
    $cash = $order->payment_method === 'cod';
    $map = 'https://www.google.com/maps/search/?api=1&query=' . urlencode((string) $order->address);
@endphp

<x-rider.layout :title="'Order ' . $order->reference">
    <p class="mt-1"><a href="{{ route('rider.dashboard') }}"
            class="text-sm font-semibold text-navy hover:text-terracotta-dark">&larr; My deliveries</a></p>

    <div class="card mt-4 space-y-4 p-5">
        <div class="flex items-center justify-between gap-3">
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold {{ $delivery->status->badge() }}">{{ $delivery->status->label() }}</span>
            <span class="text-sm">Your fee: <strong>{{ money($delivery->rider_fee) }}</strong></span>
        </div>

        <div>
            <h2 class="text-lg font-semibold">Customer</h2>
            <p class="mt-1 text-sm">{{ $order->name }}</p>
            <a href="tel:{{ preg_replace('/[^+\d]/', '', $order->phone) }}" class="btn-primary mt-2 w-full">Call
                {{ $order->phone }}</a>
        </div>

        <div>
            <h2 class="text-lg font-semibold">Address</h2>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $order->address }}</p>
            <a href="{{ $map }}" target="_blank" rel="noopener noreferrer"
                class="btn mt-2 w-full border border-navy/30 text-navy">Open in Maps</a>
            @if ($order->notes)
                <p class="mt-3 rounded-lg bg-warm p-3 text-sm"><strong>Customer note:</strong> {{ $order->notes }}</p>
            @endif
        </div>

        <div class="rounded-lg {{ $cash ? 'bg-amber-700 text-white' : 'bg-emerald-700 text-white' }} p-4 text-center">
            @if ($cash)
                <p class="text-sm">Collect in cash</p>
                <p class="font-heading text-3xl font-bold">{{ money($order->total) }}</p>
            @else
                <p class="font-heading text-xl font-bold">Paid online: do not take money</p>
            @endif
        </div>

        <div>
            <h2 class="text-lg font-semibold">Items</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($order->items as $line)
                    <li>{{ $line->quantity }} &times; {{ $line->name_en }}@if ($line->optionsLabel('en'))
                            <span class="text-xs">({{ $line->optionsLabel('en') }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($delivery->status->value === 'assigned')
            <form method="POST" action="{{ route('rider.deliveries.pickup', $delivery) }}">
                @csrf
                <button type="submit" class="btn-accent w-full !py-4 text-base">I have picked up the order</button>
            </form>
        @elseif ($delivery->status->value === 'picked_up')
            <form method="POST" action="{{ route('rider.deliveries.deliver', $delivery) }}"
                data-confirm="{{ $cash ? 'Confirm you handed over the food and collected ' . money($order->total) . '?' : 'Confirm you handed over the food?' }}">
                @csrf
                <button type="submit"
                    class="btn-accent w-full !py-4 text-base">Delivered{{ $cash ? ' and cash collected' : '' }}</button>
            </form>
        @endif

        @if ($delivery->status->isOpen())
            <form method="POST" action="{{ route('rider.deliveries.problem', $delivery) }}"
                class="border-t border-navy/10 pt-4">
                @csrf
                <label for="reason" class="block text-sm font-medium text-navy">Cannot deliver? Tell the office
                    why</label>
                <input id="reason" name="reason" required minlength="3" maxlength="255"
                    placeholder="e.g. customer not answering"
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2.5 text-sm focus:border-terracotta">
                <p class="mt-1 text-sm text-red-700">{{ $errors->first('reason') }}</p>
                <button type="submit" class="btn mt-2 w-full border border-red-700 text-red-700 hover:bg-red-50">Report
                    a problem</button>
            </form>
        @endif
    </div>

    <dialog id="confirm-dialog" aria-labelledby="confirm-title"
        class="m-auto w-full max-w-sm rounded-2xl bg-surface p-6 text-charcoal backdrop:bg-black/60">
        <h2 id="confirm-title" class="text-xl font-semibold">Are you sure?</h2>
        <p data-confirm-text class="mt-2 text-sm"></p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-confirm-no class="btn border border-navy/20 text-navy">Cancel</button>
            <button type="button" data-confirm-yes class="btn-accent">Yes</button>
        </div>
    </dialog>
</x-rider.layout>
