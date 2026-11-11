@php
    $actions = [
        'confirmed' => ['Confirm', 'btn-primary', null],
        'rejected' => [
            'Reject',
            'btn bg-red-700 text-white hover:bg-red-800',
            'Reject this reservation? The guest will be emailed.',
        ],
        'cancelled' => [
            'Cancel reservation',
            'btn border border-red-700 text-red-700 hover:bg-red-50',
            'Cancel this reservation? The guest will be emailed.',
        ],
    ];
@endphp

<x-admin.layout :title="'Reservation ' . $reservation->reference">
    <div class="card mt-6 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold {{ $reservation->status->badge() }}">{{ ucfirst($reservation->status->value) }}</span>
            <a href="{{ route('admin.reservations.index') }}"
                class="text-sm font-semibold text-navy hover:text-terracotta-dark">&larr; All reservations</a>
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-semibold text-navy">Guest</dt>
                <dd>{{ $reservation->name }}</dd>
            </div>

            <div>
                <dt class="font-semibold text-navy">Reserved table</dt>
                <dd>{{ $reservation->diningTable?->name ?? 'Not assigned' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Phone</dt>
                <dd><a href="tel:{{ preg_replace('/[^+\d]/', '', $reservation->phone) }}"
                        class="hover:text-terracotta-dark">{{ $reservation->phone }}</a></dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Email</dt>
                <dd><a href="mailto:{{ $reservation->email }}"
                        class="hover:text-terracotta-dark">{{ $reservation->email }}</a></dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Guests</dt>
                <dd>{{ $reservation->party_size }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Date and time</dt>
                <dd>{{ $reservation->reserved_at->format('l, d F Y, g:i A') }} ({{ $reservation->duration_minutes }}
                    min)</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Language</dt>
                <dd>{{ strtoupper($reservation->locale) }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Received</dt>
                <dd>{{ $reservation->created_at->format('d M Y, g:i A') }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Status changed</dt>
                <dd>{{ $reservation->status_changed_at?->format('d M Y, g:i A') ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="font-semibold text-navy">Special requests</dt>
                <dd>{{ $reservation->message ?: '—' }}</dd>
            </div>
        </dl>

        @if ($reservation->status->value === 'confirmed')
            <section class="mt-8 border-t border-navy/10 pt-6" aria-labelledby="table-title">
                <h2 id="table-title" class="text-xl font-semibold">Table</h2>

                @if ($reservation->seatedOrder)
                    <p class="mt-3 text-sm font-semibold text-green-800">Seated at
                        {{ $reservation->seatedOrder->table_name }}.
                        <a href="{{ route('admin.pos.show', $reservation->seatedOrder) }}" class="underline">Open the
                            bill</a>
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.reservations.table', $reservation) }}"
                        class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf @method('PATCH')
                        <div>
                            <label for="dining_table_id" class="block text-sm font-medium text-navy">Reserved
                                table</label>
                            <select id="dining_table_id" name="dining_table_id"
                                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                                <option value="">No table yet</option>
                                @foreach ($tables as $t)
                                    <option value="{{ $t->id }}" @selected($reservation->dining_table_id === $t->id)>
                                        {{ $t->name }} ({{ $t->seats }} seats)</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn-primary !py-2">Save table</button>
                    </form>

                    <form method="POST" action="{{ route('admin.reservations.seat', $reservation) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn-accent disabled:opacity-40" @disabled(!$reservation->dining_table_id)>Seat
                            the guests and open a bill</button>
                        @unless ($reservation->dining_table_id)
                            <p class="mt-1 text-xs">Choose a table first.</p>
                        @endunless
                    </form>
                @endif
            </section>
        @endif

        @if ($reservation->status->transitions())
            <div class="mt-8 flex flex-wrap gap-3 border-t border-navy/10 pt-6">
                @foreach ($reservation->status->transitions() as $next)
                    @php [$label, $class, $confirm] = $actions[$next->value]; @endphp
                    <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}"
                        @if ($confirm) data-confirm="{{ $confirm }}" @endif>
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $next->value }}">
                        <button type="submit" class="{{ $class }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</x-admin.layout>
