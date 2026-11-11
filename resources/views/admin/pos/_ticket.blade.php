@php
    $closed = $order->payment_status !== 'unpaid' || in_array($order->status->value, ['completed', 'cancelled'], true);
    $unsent = $order->items->contains(fn($l) => $l->quantity > $l->sent_qty);
@endphp

<div class="card p-4">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-lg font-semibold">{{ $order->table_name ?: 'Takeaway' }}</h2>
        <span class="font-mono text-xs">{{ $order->reference }}</span>
    </div>

    <ul class="mt-3 divide-y divide-navy/10 text-sm">
        @forelse ($order->items as $line)
            <li class="py-2">
                <div class="flex justify-between gap-2">
                    <span class="font-semibold text-navy">{{ $line->name_en }}</span>
                    <span>{{ money($line->line_total) }}</span>
                </div>
                @if ($line->optionsLabel('en'))
                    <p class="text-xs">{{ $line->optionsLabel('en') }}</p>
                @endif
                <div class="mt-1 flex flex-wrap items-center gap-1">
                    <button type="button" data-line-set
                        data-url="{{ route('admin.pos.lines.update', [$order, $line]) }}"
                        data-qty="{{ $line->quantity - 1 }}" aria-label="One less {{ $line->name_en }}"
                        class="h-8 w-8 rounded-full border border-navy/20 text-navy hover:border-terracotta">&minus;</button>
                    <span class="w-8 text-center font-semibold">{{ $line->quantity }}</span>
                    <button type="button" data-line-set
                        data-url="{{ route('admin.pos.lines.update', [$order, $line]) }}"
                        data-qty="{{ $line->quantity + 1 }}" aria-label="One more {{ $line->name_en }}"
                        class="h-8 w-8 rounded-full border border-navy/20 text-navy hover:border-terracotta">+</button>
                    <button type="button" data-line-remove
                        data-url="{{ route('admin.pos.lines.destroy', [$order, $line]) }}"
                        aria-label="Remove {{ $line->name_en }}"
                        class="ml-2 text-xs font-semibold text-red-700 hover:underline">Remove</button>
                    @if ($line->ready_qty > 0 && $line->ready_qty >= $line->sent_qty)
                        <span class="ml-auto text-xs font-bold text-green-800">ready to serve</span>
                    @elseif ($line->sent_qty >= $line->quantity)
                        <span class="ml-auto text-xs font-semibold text-green-800">sent</span>
                    @elseif ($line->sent_qty > 0)
                        <span class="ml-auto text-xs font-semibold text-terracotta-dark">{{ $line->sent_qty }}
                            sent</span>
                    @endif
                </div>
            </li>
        @empty
            <li class="py-6 text-center">Tap a dish to add it.</li>
        @endforelse
    </ul>

    <dl class="mt-3 space-y-1 border-t border-navy/10 pt-3 text-sm">
        <div class="flex justify-between">
            <dt>Subtotal</dt>
            <dd>{{ money($order->subtotal) }}</dd>
        </div>
        @if ((float) $order->discount > 0)
            <div class="flex justify-between">
                <dt>Discount{{ $order->discount_percent !== null ? ' (' . rtrim(rtrim((string) $order->discount_percent, '0'), '.') . '%)' : '' }}
                </dt>
                <dd>&minus;{{ money($order->discount) }}</dd>
            </div>
        @endif
        @if ((float) $order->service_charge > 0)
            <div class="flex justify-between">
                <dt>Service charge</dt>
                <dd>{{ money($order->service_charge) }}</dd>
            </div>
        @endif
        <div class="flex justify-between text-lg font-bold text-navy">
            <dt>Total</dt>
            <dd>{{ money($order->total) }}</dd>
        </div>
    </dl>

    @unless ($closed)
        <form method="POST" action="{{ route('admin.pos.discount', $order) }}" data-pos-form
            class="mt-3 flex flex-wrap items-end gap-2 text-sm">
            @csrf
            <div>
                <label for="disc-value" class="block text-xs font-medium text-navy">Discount</label>
                <input id="disc-value" name="value" type="number" step="0.01" min="0" value="0"
                    class="w-20 rounded-lg border border-navy/20 bg-surface px-2 py-1.5">
            </div>
            <select name="type" aria-label="Discount type"
                class="rounded-lg border border-navy/20 bg-surface px-2 py-1.5">
                <option value="percent">%</option>
                <option value="fixed">{{ config('restaurant.currency_symbol') }}</option>
            </select>
            <input name="reason" maxlength="120" placeholder="Reason" aria-label="Discount reason"
                class="min-w-24 flex-1 rounded-lg border border-navy/20 bg-surface px-2 py-1.5">
            <button type="submit" class="btn border border-navy/30 !px-3 !py-1.5 text-navy">Apply</button>
        </form>

        <div class="mt-4 grid grid-cols-2 gap-2">
            <form method="POST" action="{{ route('admin.pos.send', $order) }}" target="_blank" data-pos-send>
                @csrf
                <button type="submit" @disabled(!$unsent) class="btn-accent w-full !py-3 disabled:opacity-40">Send
                    to kitchen</button>
            </form>
            <a href="{{ route('admin.orders.print', ['order' => $order, 'type' => 'receipt', 'autoprint' => 1]) }}"
                target="_blank" rel="noopener" class="btn w-full border border-navy/30 !py-3 text-navy">Print bill</a>
            <a href="{{ route('admin.pos.pay', $order) }}"
                class="btn-primary col-span-2 w-full !py-3 {{ $order->items->isEmpty() ? 'pointer-events-none opacity-40' : '' }}"
                @if ($order->items->isEmpty()) aria-disabled="true" @endif>Take payment</a>
        </div>

        <form method="POST" action="{{ route('admin.pos.cancel', $order) }}" data-confirm="Cancel this whole bill?"
            class="mt-3 text-center">
            @csrf
            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Cancel bill</button>
        </form>
    @endunless
</div>
