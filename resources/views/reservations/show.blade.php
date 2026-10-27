@php
    $badge = match ($reservation->status->value) {
        'confirmed' => 'bg-green-800 text-white',
        'rejected', 'cancelled' => 'bg-charcoal text-white',
        default => 'bg-terracotta-dark text-white',
    };
@endphp

<x-layouts.app :title="__('forms.reservation.show.title')">
    <section class="mx-auto max-w-xl px-6 py-14">
        <div class="card p-8">
            <h1 class="text-3xl font-bold">{{ __('forms.reservation.show.title') }}</h1>
            <p class="mt-3">{{ __('forms.reservation.show.thanks', ['name' => $reservation->name]) }}</p>

            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="font-semibold">{{ __('forms.reservation.show.reference') }}</dt>
                    <dd>{{ $reservation->reference }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-semibold">{{ __('forms.reservation.show.status') }}</dt>
                    <dd><span
                            class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $reservation->status->label() }}</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-semibold">{{ __('forms.reservation.show.when') }}</dt>
                    <dd class="text-right">
                        {{ $reservation->reserved_at->translatedFormat('l, d F Y') }}<br>{{ $reservation->reserved_at->format('g:i A') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-semibold">{{ __('forms.reservation.show.guests') }}</dt>
                    <dd>{{ $reservation->party_size }}</dd>
                </div>
            </dl>

            <p class="mt-6 text-sm">{{ __('forms.reservation.show.next') }}</p>
            <a href="{{ route('home') }}" class="btn-primary mt-6">{{ __('forms.reservation.show.back') }}</a>
        </div>
    </section>
</x-layouts.app>
