@php $closed = $order->payment_status !== 'unpaid' || in_array($order->status->value, ['completed', 'cancelled'], true); @endphp

<x-admin.layout :title="$order->table_name ? 'Bill: ' . $order->table_name : 'Takeaway bill'">
    <p class="mt-1 text-sm"><a href="{{ route('admin.pos.index') }}"
            class="font-semibold text-navy hover:text-terracotta-dark">&larr; Tables</a></p>

    @if ($closed)
        <div class="card mt-4 p-6">
            <p class="text-lg font-semibold text-navy">This bill is
                {{ $order->status->value === 'cancelled' ? 'cancelled' : 'closed' }}.</p>
            <p class="mt-1 text-sm">Total {{ money($order->total) }} &middot; payment: {{ $order->payment_status }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.orders.print', ['order' => $order, 'type' => 'receipt', 'autoprint' => 1]) }}"
                    target="_blank" rel="noopener" class="btn-primary">Print receipt</a>
                <a href="{{ route('admin.orders.show', $order) }}" class="btn border border-navy/30 text-navy">Order
                    details</a>
            </div>
            @if ($order->payment_status === 'paid' && $order->status->value !== 'cancelled')
                @can('manage-menu')
                    <form method="POST" action="{{ route('admin.pos.void', $order) }}"
                        class="mt-6 flex flex-wrap items-end gap-3 border-t border-navy/10 pt-4"
                        data-confirm="Void this paid bill? The payment goes back out of the drawer and the stock returns.">
                        @csrf
                        <div>
                            <label for="void-reason" class="block text-sm font-medium text-navy">Void this bill
                                (manager)</label>
                            <input id="void-reason" name="reason" required minlength="3" maxlength="120"
                                placeholder="Reason"
                                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                        </div>
                        <button type="submit"
                            class="btn border border-red-700 !py-2 text-red-700 hover:bg-red-50">Void</button>
                    </form>
                @endcan
            @endif
        </div>
    @else
        <div id="pos-root" data-add-url="{{ route('admin.pos.lines.store', $order) }}"
            class="mt-4 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2" aria-label="Menu">
                <label for="pos-search" class="sr-only">Search the menu</label>
                <input id="pos-search" type="search" data-pos-search placeholder="Search a dish…" autocomplete="off"
                    class="w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" data-pos-cat="" aria-current="true" class="chip">All</button>
                    @foreach ($categories as $category)
                        <button type="button" data-pos-cat="{{ $category->id }}" aria-current="false"
                            class="chip">{{ $category->name_en }}</button>
                    @endforeach
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($categories as $category)
                        @foreach ($category->items as $item)
                            @php
                                $options = [
                                    'variations' => $item->activeVariations
                                        ->map(
                                            fn($v) => [
                                                'id' => $v->id,
                                                'name' => $v->name_en,
                                                'price' => money($v->price),
                                            ],
                                        )
                                        ->values()
                                        ->all(),
                                    'addons' => $item->activeAddons
                                        ->map(
                                            fn($a) => [
                                                'id' => $a->id,
                                                'name' => $a->name_en,
                                                'price' => money($a->price),
                                            ],
                                        )
                                        ->values()
                                        ->all(),
                                ];
                            @endphp
                            <button type="button" data-pos-item data-id="{{ $item->id }}"
                                data-name="{{ $item->name_en }}" data-cat="{{ $category->id }}"
                                data-options="{{ json_encode($options) }}"
                                class="rounded-xl border border-navy/15 bg-surface p-3 text-left shadow-sm transition hover:border-terracotta hover:shadow">
                                <span class="block font-semibold text-navy">{{ $item->name_en }}</span>
                                <span
                                    class="mt-1 block text-sm">{{ $item->activeVariations->isNotEmpty() ? 'From ' . money($item->activeVariations->min('price')) : money($item->price) }}</span>
                            </button>
                        @endforeach
                    @endforeach
                </div>
            </section>

            <aside>
                <div id="pos-ticket">@include('admin.pos._ticket')</div>

                <form method="POST" action="{{ route('admin.pos.update', $order) }}"
                    class="card mt-4 space-y-3 p-4 text-sm">
                    @csrf @method('PATCH')
                    <h2 class="text-base font-semibold">Bill details</h2>
                    @if ($order->type === \App\Enums\OrderType::DineIn)
                        <div>
                            <label for="dining_table_id" class="block font-medium text-navy">Table</label>
                            <select id="dining_table_id" name="dining_table_id"
                                class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2">
                                @foreach ($tables as $t)
                                    <option value="{{ $t->id }}" @selected($order->dining_table_id === $t->id)>
                                        {{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="guests" class="block font-medium text-navy">Guests</label>
                            <input id="guests" name="guests" type="number" min="1" max="50"
                                value="{{ $order->guests }}"
                                class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2">
                        </div>
                    @endif
                    <div>
                        <label for="cust-name" class="block font-medium text-navy">Customer name</label>
                        <input id="cust-name" name="name" maxlength="100" value="{{ $order->name }}"
                            class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2">
                    </div>
                    <div>
                        <label for="cust-phone" class="block font-medium text-navy">Phone (optional)</label>
                        <input id="cust-phone" name="phone" type="tel" maxlength="20" value="{{ $order->phone }}"
                            class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2">
                    </div>
                    <div>
                        <label for="notes" class="block font-medium text-navy">Notes for the kitchen</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="300"
                            class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-3 py-2">{{ $order->notes }}</textarea>
                    </div>
                    <button type="submit" class="btn-primary !py-2">Save details</button>
                </form>
            </aside>
        </div>

        <dialog id="pos-options" aria-labelledby="pos-options-title"
            class="m-auto w-full max-w-md rounded-2xl bg-surface p-6 text-charcoal backdrop:bg-black/60">
            <form id="pos-options-form">
                <input type="hidden" name="item_id" value="">
                <h2 id="pos-options-title" class="text-xl font-semibold"></h2>
                <div data-pos-options-body class="mt-4 space-y-4 text-sm"></div>
                <div class="mt-4">
                    <label for="pos-qty" class="block text-sm font-medium text-navy">Quantity</label>
                    <input id="pos-qty" name="quantity" type="number" min="1" max="50"
                        value="1"
                        class="mt-1 w-24 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" data-pos-options-close
                        class="btn border border-navy/20 text-navy">Cancel</button>
                    <button type="submit" class="btn-accent">Add to bill</button>
                </div>
            </form>
        </dialog>
    @endif
</x-admin.layout>
