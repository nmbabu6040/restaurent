@php
    use App\Enums\DeliveryStatus;

    $delivery = $order->delivery;
    $canAssign =
        in_array($order->status->value, ['confirmed', 'preparing', 'out_for_delivery'], true) &&
        $delivery?->status !== DeliveryStatus::Delivered;
@endphp

@if ($order->type === \App\Enums\OrderType::Delivery)
    <section class="mt-8 border-t border-navy/10 pt-6" aria-labelledby="delivery-title">
        <h2 id="delivery-title" class="text-xl font-semibold">Delivery</h2>

        @if ($delivery)
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-navy">Rider</dt>
                    <dd>{{ $delivery->rider->name }} &middot; <a
                            href="tel:{{ preg_replace('/[^+\d]/', '', $delivery->rider->phone) }}"
                            class="hover:text-terracotta-dark">{{ $delivery->rider->phone }}</a></dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">Status</dt>
                    <dd><span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $delivery->status->badge() }}">{{ $delivery->status->label() }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">Assigned</dt>
                    <dd>{{ $delivery->assigned_at?->format('d M, g:i A') }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">Picked up</dt>
                    <dd>{{ $delivery->picked_up_at?->format('d M, g:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">Delivered</dt>
                    <dd>{{ $delivery->delivered_at?->format('d M, g:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">Rider fee / cash collected</dt>
                    <dd>{{ money($delivery->rider_fee) }} / {{ money($delivery->cash_collected) }}</dd>
                </div>
            </dl>
            @if ($delivery->status === DeliveryStatus::Failed)
                <p role="alert" class="mt-3 rounded-lg bg-red-700 p-3 text-sm font-semibold text-white">Rider reported
                    a problem: {{ $delivery->failed_reason }}. Send another rider below, or cancel the order.</p>
            @endif
        @endif

        @if ($canAssign)
            <form method="POST" action="{{ route('admin.orders.rider', $order) }}"
                class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="rider_id"
                        class="block text-sm font-medium text-navy">{{ $delivery ? 'Give to another rider' : 'Assign a rider' }}</label>
                    <select id="rider_id" name="rider_id" required
                        class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta">
                        <option value="">Choose a rider…</option>
                        @foreach ($riders as $rider)
                            <option value="{{ $rider->id }}" @selected($delivery?->rider_id === $rider->id)>{{ $rider->name }}
                                ({{ $rider->is_online ? 'online' : 'offline' }}, fee
                                {{ money($rider->fee_per_delivery) }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary !py-2">{{ $delivery ? 'Reassign' : 'Assign' }}</button>
            </form>
            @if ($riders->isEmpty())
                <p class="mt-2 text-sm">No active riders. <a href="{{ route('admin.riders.create') }}"
                        class="font-semibold text-navy underline">Add one</a>.</p>
            @endif
        @elseif (!$delivery)
            <p class="mt-3 text-sm">Confirm the order first, then you can assign a rider.</p>
        @endif
    </section>
@endif
