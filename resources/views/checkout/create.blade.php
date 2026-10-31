@php
    $first = $types[0];
    $discount = $summary['discount'];
@endphp

<x-layouts.app :title="__('orders.form.title')" :noindex="true">
    <section class="mx-auto max-w-6xl px-6 py-12">
        <h1 class="text-4xl font-bold">{{ __('orders.form.title') }}</h1>

        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" data-ajax-form
                data-totals="{{ json_encode($totals) }}"
                @if ($zones->isNotEmpty()) data-zones="{{ json_encode($zoneTotals) }}" @endif
                data-msg-error="{{ __('forms.common.error') }}" data-msg-throttled="{{ __('forms.common.throttled') }}"
                class="card space-y-5 p-6 sm:p-8 lg:col-span-2">
                @csrf

                <p data-error-for="cart" role="alert" class="text-sm font-semibold text-red-700"></p>

                @if (count($types) > 1)
                    <fieldset>
                        <legend class="text-sm font-medium text-navy">{{ __('orders.form.how') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach ($types as $t)
                                <label
                                    class="flex items-center gap-2 rounded-lg border border-navy/20 bg-surface px-4 py-3 text-sm">
                                    <input type="radio" name="type" value="{{ $t->value }}" class="accent-navy"
                                        @checked(old('type', $first->value) === $t->value)> {{ $t->label() }}
                                </label>
                            @endforeach
                        </div>
                        <p data-error-for="type" class="mt-1 text-sm text-red-700"></p>
                    </fieldset>
                @else
                    <input type="hidden" name="type" value="{{ $first->value }}">
                @endif

                <h2 class="text-xl font-semibold">{{ __('orders.form.details') }}</h2>
                <x-form.field name="name" :label="__('orders.form.name')" :required="true" maxlength="100" autocomplete="name" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field name="phone" type="tel" :label="__('orders.form.phone')" :required="true" maxlength="20"
                        autocomplete="tel" />
                    <x-form.field name="email" type="email" :label="__('orders.form.email')" maxlength="150"
                        autocomplete="email" />
                </div>

                @if ($zones->isNotEmpty())
                    <div id="zone-wrap" class="mb-5">
                        <label for="delivery_zone_id"
                            class="block text-sm font-medium text-navy">{{ __('orders.zone.label') }}<span
                                aria-hidden="true" class="text-terracotta-dark"> *</span></label>
                        <select id="delivery_zone_id" name="delivery_zone_id"
                            class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                            <option value="">{{ __('orders.zone.choose') }}</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" @selected((int) old('delivery_zone_id') === $zone->id)>
                                    {{ $zone->tr('name') }}
                                    ({{ (float) $zone->fee > 0 ? money($zone->fee) : __('orders.free') }})</option>
                            @endforeach
                        </select>
                        <p data-error-for="delivery_zone_id" class="mt-1 text-sm text-red-700"></p>
                        <p id="zone-note" aria-live="polite" class="mt-1 text-sm font-semibold text-terracotta-dark">
                        </p>
                    </div>
                @endif

                <div id="address-wrap">
                    <x-form.textarea name="address" :label="__('orders.form.address')" rows="3" maxlength="300" />
                </div>

                <x-form.textarea name="notes" :label="__('orders.form.notes')" rows="2" maxlength="500" />

                <fieldset>
                    <legend class="text-xl font-semibold text-navy">{{ __('orders.form.payment') }}</legend>
                    @if ($onlinePayment)
                        <div class="mt-3 space-y-2">
                            <label
                                class="flex items-start gap-2 rounded-lg border border-navy/20 bg-surface px-4 py-3 text-sm">
                                <input type="radio" name="payment_method" value="cod" class="mt-0.5 accent-navy"
                                    @checked(old('payment_method', 'cod') === 'cod')>
                                <span>{{ __('orders.form.payment_cod') }}</span>
                            </label>
                            <label
                                class="flex items-start gap-2 rounded-lg border border-navy/20 bg-surface px-4 py-3 text-sm">
                                <input type="radio" name="payment_method" value="sslcommerz"
                                    class="mt-0.5 accent-navy" @checked(old('payment_method') === 'sslcommerz')>
                                <span>{{ __('orders.form.payment_online') }}</span>
                            </label>
                        </div>
                        <p data-error-for="payment_method" class="mt-1 text-sm text-red-700"></p>
                    @else
                        <input type="hidden" name="payment_method" value="cod">
                        <p class="mt-2 text-sm">{{ __('orders.form.payment_cod') }}</p>
                    @endif
                </fieldset>

                <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1"
                        autocomplete="off"></div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-accent">{{ __('orders.form.place') }}</button>
                    <a href="{{ route('cart.show') }}"
                        class="btn border border-navy/20 text-navy">{{ __('orders.form.back') }}</a>
                </div>
            </form>

            <aside class="card h-fit p-6">
                <h2 class="text-xl font-semibold">{{ __('orders.form.items') }}</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($summary['lines'] as $line)
                        <li class="flex justify-between gap-3">
                            <span>{{ $line['qty'] }} &times; {{ $line['item']->name }}<x-orders.options
                                    :variation="$line['variation']" :addons="$line['addons']" /></span>
                            <span>{{ money_c($line['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-2 border-t border-navy/10 pt-4 text-sm">
                    <div class="flex justify-between">
                        <dt>{{ __('orders.subtotal') }}</dt>
                        <dd>{{ money_c($summary['subtotal']) }}</dd>
                    </div>
                    @if ($discount > 0)
                        <div class="flex justify-between">
                            <dt>{{ __('orders.discount') }}@if ($summary['coupon'])
                                    ({{ $summary['coupon']['code'] }})
                                @endif
                            </dt>
                            <dd>&minus;{{ money_c($discount) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt>{{ __('orders.delivery_fee') }}</dt>
                        <dd id="sum-fee">{{ $totals[$first->value]['fee'] }}</dd>
                    </div>
                    <div class="flex justify-between text-base font-semibold text-navy">
                        <dt>{{ __('orders.total') }}</dt>
                        <dd id="sum-total">{{ $totals[$first->value]['total'] }}</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </section>
</x-layouts.app>
