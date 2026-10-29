@php
    use App\Enums\OrderStatus;

    $steps = OrderStatus::steps($order->type);
    $current = array_search($order->status, $steps, true);
    $delivery = $order->delivery;
    $showRider =
        $delivery &&
        $delivery->rider &&
        in_array($delivery->status->value, ['assigned', 'picked_up'], true) &&
        in_array($order->status->value, ['confirmed', 'preparing', 'out_for_delivery'], true);
    $needsPayment =
        $order->payment_method === 'sslcommerz' &&
        $order->payment_status !== 'paid' &&
        $order->status !== OrderStatus::Cancelled &&
        filled(config('services.sslcommerz.store_id'));
@endphp

<x-layouts.app :title="__('orders.show.title')" :noindex="true">
    <section class="mx-auto max-w-3xl px-6 py-12" data-order-status-url="{{ route('orders.status', $order->token) }}"
        data-status="{{ $order->status->value . '|' . ($order->delivery?->status->value ?? '') }}">
        <div class="card p-6 sm:p-8">
            <h1 class="text-3xl font-bold">{{ __('orders.show.thanks', ['name' => $order->name]) }}</h1>
            <p class="mt-2">{{ __('orders.show.received') }}</p>

            @if ($needsPayment)
                <div class="mt-6 rounded-lg bg-terracotta/10 p-4">
                    <p class="text-sm font-semibold">{{ __('orders.payment.pending_notice') }}</p>
                    <form method="POST" action="{{ route('payments.start', $order->token) }}" class="mt-3">
                        @csrf
                        <button type="submit"
                            class="btn-accent">{{ __('orders.payment.pay_now', ['amount' => money($order->total)]) }}</button>
                    </form>
                </div>
            @endif

            @include('admin.orders._delivery')

            @if ($order->status === OrderStatus::Cancelled)
                <p class="mt-6 rounded-lg bg-charcoal p-4 text-sm font-semibold text-white">
                    {{ __('orders.show.cancelled') }}</p>
            @else
                <ol class="mt-6 grid gap-2 sm:grid-cols-5" aria-label="{{ __('orders.show.status') }}">
                    @foreach ($steps as $i => $step)
                        <li @if ($i === $current) aria-current="step" @endif
                            class="rounded-lg p-3 text-center text-xs font-semibold {{ $i <= $current ? 'bg-navy text-white' : 'bg-navy/10 text-navy' }}">
                            {{ $step->label() }}
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($showRider)
                <div class="mt-6 rounded-lg bg-warm p-4 text-sm">
                    <p class="font-semibold text-navy">{{ __('orders.rider.title') }}</p>
                    <p class="mt-1">{{ $delivery->rider->name }}</p>
                    @if ($delivery->status->value === 'picked_up')
                        <p class="mt-1">{{ __('orders.rider.on_the_way') }}
                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $delivery->rider->phone) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">{{ $delivery->rider->phone }}</a>
                        </p>
                    @else
                        <p class="mt-1">{{ __('orders.rider.assigned') }}</p>
                    @endif
                </div>
            @endif

            <dl class="mt-8 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-navy">{{ __('orders.show.reference') }}</dt>
                    <dd class="font-mono">{{ $order->reference }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('orders.show.status') }}</dt>
                    <dd>{{ $order->status->label() }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('orders.show.type') }}</dt>
                    <dd>{{ $order->type->label() }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-navy">{{ __('orders.show.placed') }}</dt>
                    <dd>{{ $order->created_at->translatedFormat('d M Y, g:i A') }}</dd>
                </div>
                @if ($order->address)
                    <div class="sm:col-span-2">
                        <dt class="font-semibold text-navy">{{ __('orders.show.address') }}</dt>
                        <dd>{{ $order->address }}</dd>
                    </div>
                @endif
                @if ($order->notes)
                    <div class="sm:col-span-2">
                        <dt class="font-semibold text-navy">{{ __('orders.show.notes') }}</dt>
                        <dd>{{ $order->notes }}</dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt class="font-semibold text-navy">{{ __('orders.show.payment') }}</dt>
                    <dd>{{ __('orders.payment.' . $order->payment_method) }} &middot;
                        {{ __('orders.payment_status.' . $order->payment_status) }}</dd>
                </div>
            </dl>

            <h2 class="mt-8 text-xl font-semibold">{{ __('orders.show.items') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($order->items as $line)
                    <li class="flex justify-between gap-3">
                        <span>{{ $line->quantity }} &times; {{ $line->tr('name') }}@if ($line->optionsLabel(app()->getLocale()))
                                <span
                                    class="block text-xs text-charcoal/80">{{ $line->optionsLabel(app()->getLocale()) }}</span>
                            @endif
                        </span>
                        <span>{{ money($line->line_total) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-4 space-y-2 border-t border-navy/10 pt-4 text-sm">
                <div class="flex justify-between">
                    <dt>{{ __('orders.subtotal') }}</dt>
                    <dd>{{ money($order->subtotal) }}</dd>
                </div>
                @if ((float) $order->discount > 0)
                    <div class="flex justify-between">
                        <dt>{{ __('orders.discount') }}@if ($order->coupon_code)
                                ({{ $order->coupon_code }})
                            @endif
                        </dt>
                        <dd>&minus;{{ money($order->discount) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt>{{ __('orders.delivery_fee') }}</dt>
                    <dd>{{ (float) $order->delivery_fee > 0 ? money($order->delivery_fee) : __('orders.free') }}</dd>
                </div>
                <div class="flex justify-between text-base font-semibold text-navy">
                    <dt>{{ __('orders.total') }}</dt>
                    <dd>{{ money($order->total) }}</dd>
                </div>
            </dl>

            <a href="{{ route('orders.track') }}"
                class="mt-6 inline-block text-sm font-semibold text-navy hover:text-terracotta-dark">{{ __('orders.show.track_other') }}</a>
        </div>
    </section>
</x-layouts.app>
