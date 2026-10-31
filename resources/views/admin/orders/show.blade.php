@php
    $actions = [
        'confirmed' => ['Confirm order', 'btn-primary', null],
        'preparing' => ['Start preparing', 'btn-primary', null],
        'ready' => ['Mark ready for pickup', 'btn-primary', null],
        'out_for_delivery' => ['Out for delivery', 'btn-primary', null],
        'completed' => ['Mark completed', 'btn-primary', null],
        'cancelled' => [
            'Cancel order',
            'btn border border-red-700 text-red-700 hover:bg-red-50',
            'Cancel this order? The customer is emailed if they gave an address.',
        ],
    ];

    $transitions = $order->status->transitions($order->type);
@endphp

<x-admin.layout :title="'Order ' . $order->reference">
    <div class="card mt-6 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold {{ $order->status->badge() }}">{{ $order->status->adminLabel() }}</span>
            <a href="{{ route('admin.orders.index') }}"
                class="text-sm font-semibold text-navy hover:text-terracotta-dark">&larr; All orders</a>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('admin.orders.print', ['order' => $order, 'type' => 'kot', 'autoprint' => 1]) }}"
                target="_blank" rel="noopener" class="btn border border-navy/30 !py-2 text-navy">Print kitchen ticket</a>
            <a href="{{ route('admin.orders.print', ['order' => $order, 'type' => 'receipt', 'autoprint' => 1]) }}"
                target="_blank" rel="noopener" class="btn border border-navy/30 !py-2 text-navy">Print receipt</a>
            <a href="{{ route('admin.orders.invoice', $order) }}"
                class="btn border border-navy/30 !py-2 text-navy">Download bill (PDF)</a>
            <a href="{{ route('admin.orders.challan', $order) }}"
                class="btn border border-navy/30 !py-2 text-navy">Download challan (PDF)</a>
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-semibold text-navy">Customer</dt>
                <dd>{{ $order->name }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Phone</dt>
                <dd><a href="tel:{{ preg_replace('/[^+\d]/', '', $order->phone) }}"
                        class="hover:text-terracotta-dark">{{ $order->phone }}</a></dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Email</dt>
                <dd>{{ $order->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Type</dt>
                <dd>{{ ucfirst($order->type->value) }}</dd>
            </div>
            @if ($order->address)
                <div class="sm:col-span-2">
                    <dt class="font-semibold text-navy">Delivery address</dt>
                    <dd class="whitespace-pre-line">{{ $order->address }}</dd>
                </div>
            @endif
            @if ($order->zone_en)
                <div>
                    <dt class="font-semibold text-navy">Delivery area</dt>
                    <dd>{{ $order->zone_en }}</dd>
                </div>
            @endif
            <div class="sm:col-span-2">
                <dt class="font-semibold text-navy">Notes</dt>
                <dd class="whitespace-pre-line">{{ $order->notes ?: '—' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Payment</dt>
                <dd>{{ $order->payment_method === 'cod' ? 'Cash' : 'Online (SSLCommerz)' }} &middot;
                    {{ ucfirst($order->payment_status) }}</dd>
            </div>
            @if ($order->coupon_code)
                <div>
                    <dt class="font-semibold text-navy">Coupon</dt>
                    <dd>{{ $order->coupon_code }} (&minus;{{ money($order->discount) }})</dd>
                </div>
            @endif
            <div>
                <dt class="font-semibold text-navy">Placed</dt>
                <dd>{{ $order->created_at->format('d M Y, g:i A') }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Language</dt>
                <dd>{{ strtoupper($order->locale) }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Customer tracking page</dt>
                <dd><a href="{{ route('orders.show', $order->token) }}" target="_blank" rel="noopener"
                        class="hover:text-terracotta-dark">Open</a></dd>
            </div>
        </dl>

        <div class="mt-8 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-navy/10 text-navy">
                    <tr>
                        <th scope="col" class="py-2">Item</th>
                        <th scope="col" class="py-2 text-center">Qty</th>
                        <th scope="col" class="py-2 text-right">Unit</th>
                        <th scope="col" class="py-2 text-right">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $line)
                        <tr class="border-b border-navy/5">
                            <td class="py-2">
                                {{ $line->name_en }}
                                @if ($line->name_bn)
                                    <br><span class="text-xs">{{ $line->name_bn }}</span>
                                @endif
                                @if ($line->optionsLabel('en'))
                                    <br><span class="text-xs font-semibold">{{ $line->optionsLabel('en') }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-center">{{ $line->quantity }}</td>
                            <td class="py-2 text-right">{{ money($line->unit_price) }}</td>
                            <td class="py-2 text-right">{{ money($line->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-sm">
                    <tr>
                        <td colspan="3" class="pt-3 text-right">Subtotal</td>
                        <td class="pt-3 text-right">{{ money($order->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-right">Delivery fee</td>
                        <td class="text-right">{{ money($order->delivery_fee) }}</td>
                    </tr>
                    @if ((float) $order->discount > 0)
                        <tr>
                            <td colspan="3" class="text-right">Discount</td>
                            <td class="text-right">&minus;{{ money($order->discount) }}</td>
                        </tr>
                    @endif
                    <tr class="font-semibold text-navy">
                        <td colspan="3" class="pt-1 text-right">Total</td>
                        <td class="pt-1 text-right">{{ money($order->total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($order->payments->isNotEmpty())
            <h2 class="mt-8 text-xl font-semibold">Payment attempts</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-navy/10 text-navy">
                        <tr>
                            <th scope="col" class="py-2">Transaction</th>
                            <th scope="col" class="py-2">Status</th>
                            <th scope="col" class="py-2">Bank ref</th>
                            <th scope="col" class="py-2">Paid at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->payments as $p)
                            <tr class="border-b border-navy/5">
                                <td class="py-2 font-mono text-xs">{{ $p->tran_id }}</td>
                                <td class="py-2">{{ ucfirst($p->status) }}</td>
                                <td class="py-2 text-xs">{{ $p->bank_tran_id ?: '—' }}</td>
                                <td class="py-2">{{ $p->paid_at?->format('d M, g:i A') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @include('admin.orders._delivery')

        @if ($transitions)
            <div class="mt-8 flex flex-wrap gap-3 border-t border-navy/10 pt-6">
                @foreach ($transitions as $next)
                    @php [$label, $class, $confirm] = $actions[$next->value]; @endphp
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}"
                        @if ($confirm) data-confirm="{{ $confirm }}" @endif>
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $next->value }}">
                        <button type="submit" class="{{ $class }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
            <p class="mt-3 text-xs">Marking a cash order completed also marks it as paid.</p>
            @if ($order->payment_method !== 'cod' && $order->payment_status !== 'paid')
                <p class="mt-2 text-xs font-semibold text-terracotta-dark">Online payment not received yet. You can
                    confirm this order only after it is paid.</p>
            @endif
        @endif
    </div>
</x-admin.layout>
