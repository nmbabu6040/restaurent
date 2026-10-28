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
