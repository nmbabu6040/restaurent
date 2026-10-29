@if (!empty($removed))
    <p role="alert" class="mb-4 rounded-lg bg-terracotta/10 p-4 text-sm">
        {{ __('orders.removed_notice', ['items' => implode(', ', $removed)]) }}</p>
@endif

@if (!empty($coupon_error))
    <p role="alert" class="mb-4 rounded-lg bg-terracotta/10 p-4 text-sm">{{ __('orders.coupon.dropped') }}</p>
@endif

@if ($lines->isEmpty())
    <div class="card mx-auto max-w-md p-10 text-center">
        <p class="font-heading text-xl text-navy">{{ __('orders.empty_title') }}</p>
        <p class="mt-2 text-sm">{{ __('orders.empty_text') }}</p>
        <a href="{{ route('menu.index') }}" class="btn-primary mt-6">{{ __('orders.browse_menu') }}</a>
    </div>
@else
    @php $max = config('restaurant.ordering.max_qty'); @endphp
    <div class="grid gap-8 lg:grid-cols-3">
        <ul class="space-y-4 lg:col-span-2">
            @foreach ($lines as $line)
                @php
                    $item = $line['item'];
                    $label = $item->name . ($line['variation'] ? ' (' . $line['variation']->tr('name') . ')' : '');
                @endphp
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    @if ($item->image_url)
                        <img src="{{ $item->image_url }}" alt="" width="64" height="64" loading="lazy"
                            class="h-16 w-16 rounded-lg object-cover">
                    @endif
                    <div class="min-w-0 flex-1 basis-40">
                        <a href="{{ route('menu.show', $item->slug) }}"
                            class="font-heading text-lg text-navy hover:text-terracotta-dark">{{ $item->name }}</a>
                        <x-orders.options :variation="$line['variation']" :addons="$line['addons']" />
                        <p class="text-sm">{{ money_c($line['unit']) }}</p>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" data-qty-delta="-1"
                            aria-label="{{ __('orders.decrease', ['name' => $label]) }}"
                            class="h-9 w-9 rounded-full border border-navy/20 text-lg text-navy hover:border-terracotta">&minus;</button>
                        <input type="number" data-cart-qty
                            data-url="{{ route('cart.update', ['line' => $line['key']]) }}"
                            value="{{ $line['qty'] }}" min="0" max="{{ $max }}"
                            aria-label="{{ __('orders.quantity_of', ['name' => $label]) }}"
                            class="w-14 rounded-lg border border-navy/20 bg-surface py-1.5 text-center text-sm">
                        <button type="button" data-qty-delta="1"
                            aria-label="{{ __('orders.increase', ['name' => $label]) }}"
                            class="h-9 w-9 rounded-full border border-navy/20 text-lg text-navy hover:border-terracotta">+</button>
                    </div>

                    <p class="w-24 text-right font-semibold text-navy">{{ money_c($line['total']) }}</p>

                    <form method="POST" action="{{ route('cart.remove', ['line' => $line['key']]) }}"
                        data-cart-remove>
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-red-700 hover:underline"
                            aria-label="{{ __('orders.remove_named', ['name' => $label]) }}">{{ __('orders.remove') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <aside class="card h-fit p-6">
            <h2 class="text-xl font-semibold">{{ __('orders.summary') }}</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt>{{ __('orders.subtotal') }}</dt>
                    <dd class="font-semibold text-navy">{{ money_c($subtotal) }}</dd>
                </div>
                @if ($discount > 0)
                    <div class="flex justify-between">
                        <dt>{{ __('orders.discount') }}</dt>
                        <dd class="font-semibold text-navy">&minus;{{ money_c($discount) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-navy/10 pt-2">
                        <dt>{{ __('orders.total') }}</dt>
                        <dd class="font-semibold text-navy">{{ money_c($subtotal - $discount) }}</dd>
                    </div>
                @endif
            </dl>
            <p class="mt-3 text-xs">{{ __('orders.delivery_note') }}</p>

            <div class="mt-4 border-t border-navy/10 pt-4">
                @if ($coupon)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ __('orders.coupon.label') }}: <strong>{{ $coupon['code'] }}</strong></span>
                        <form method="POST" action="{{ route('cart.coupon.remove') }}" data-cart-coupon-remove>
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="font-semibold text-red-700 hover:underline">{{ __('orders.coupon.remove') }}</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('cart.coupon.apply') }}" data-cart-coupon>
                        @csrf
                        <label for="coupon-code"
                            class="block text-sm font-medium text-navy">{{ __('orders.coupon.label') }}</label>
                        <div class="mt-1 flex gap-2">
                            <input id="coupon-code" name="code" maxlength="30" autocomplete="off"
                                placeholder="{{ __('orders.coupon.placeholder') }}"
                                aria-describedby="coupon-code-error"
                                class="w-full rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm uppercase focus:border-terracotta">
                            <button type="submit"
                                class="btn-primary !px-4 !py-2">{{ __('orders.coupon.apply') }}</button>
                        </div>
                        <p id="coupon-code-error" class="mt-1 text-sm text-red-700">{{ $errors->first('code') }}</p>
                    </form>
                @endif
            </div>

            @if ($subtotal < $min_order)
                <p class="mt-4 rounded-lg bg-terracotta/10 p-3 text-sm">
                    {{ __('orders.min_order_notice', ['amount' => money_c($min_order), 'missing' => money_c($min_order - $subtotal)]) }}
                </p>
            @else
                <a href="{{ route('checkout.create') }}"
                    class="btn-accent mt-6 w-full">{{ __('orders.checkout') }}</a>
            @endif
        </aside>
    </div>
@endif
