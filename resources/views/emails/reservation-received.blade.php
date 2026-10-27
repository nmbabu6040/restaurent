<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<body style="font-family: Arial, sans-serif; color: #292D32; line-height: 1.6;">
    <h2 style="color: #172B4D;">{{ __('forms.mail.heading') }}</h2>
    <p>{{ __('forms.mail.hello', ['name' => $reservation->name]) }}</p>
    <p>{{ __('forms.mail.body') }}</p>
    <ul>
        <li>{{ __('forms.reservation.show.reference') }}: <strong>{{ $reservation->reference }}</strong></li>
        <li>{{ __('forms.reservation.show.when') }}: {{ $reservation->reserved_at->translatedFormat('l, d F Y') }},
            {{ $reservation->reserved_at->format('g:i A') }}</li>
        <li>{{ __('forms.reservation.show.guests') }}: {{ $reservation->party_size }}</li>
    </ul>
    <p><a href="{{ route('reservations.show', $reservation->reference) }}">{{ __('forms.mail.view') }}</a></p>
    <p>{{ setting('name', config('app.name')) }}</p>
</body>

</html>
