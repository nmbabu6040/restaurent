<x-admin.layout :title="'Payment: ' . ($order->table_name ?: 'Takeaway')">
    <p class="mt-1 text-sm"><a href="{{ route('admin.pos.show', $order) }}"
            class="font-semibold text-navy hover:text-terracotta-dark">&larr; Back to the bill</a></p>

    <form id="pos-pay-form" method="POST" action="{{ route('admin.pos.pay.store', $order) }}"
        data-due="{{ number_format($due, 2, '.', '') }}" data-symbol="{{ config('restaurant.currency_symbol') }}"
        class="card mt-4 space-y-5 p-6">
        @csrf

        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.stat label="Bill total" :value="money($due)" icon="cash" tone="indigo" />
            <div class="rounded-2xl bg-linear-to-br from-amber-700 to-orange-700 p-5 text-white shadow-card">
                <p class="text-sm font-medium text-white/90">Still to pay</p>
                <p class="mt-1 font-heading text-3xl font-bold" data-pay-remaining aria-live="polite">
                    {{ money($due) }}</p>
            </div>
            <div class="rounded-2xl bg-linear-to-br from-emerald-700 to-teal-700 p-5 text-white shadow-card">
                <p class="text-sm font-medium text-white/90">Change to give back</p>
                <p class="mt-1 font-heading text-3xl font-bold" data-pay-change aria-live="polite">{{ money(0) }}
                </p>
            </div>
        </div>

        <div id="pay-rows" class="space-y-3">
            @include('admin.pos._pay-row', [
                'i' => 0,
                'row' => old('payments.0', [
                    'method' => 'cash',
                    'amount' => number_format($due, 2, '.', ''),
                    'tendered' => number_format($due, 2, '.', ''),
                ]),
            ])
        </div>
        <template id="pay-row-tpl">@include('admin.pos._pay-row', ['i' => '__INDEX__', 'row' => []])</template>

        <div class="flex flex-wrap gap-3">
            <button type="button" id="pay-add" class="btn border border-navy/30 !py-2 text-navy">Split: add another
                payment</button>
            <button type="submit" class="btn-accent">Complete payment and print receipt</button>
        </div>
        <p class="text-xs">The payments must add up to the bill. Cash given is only for working out the change.</p>
    </form>
</x-admin.layout>
