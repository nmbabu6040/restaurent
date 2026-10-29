<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<body style="font-family: Arial, sans-serif; color: #292D32; line-height: 1.6;">
    <h2 style="color: #172B4D;">{{ $kind === 'placed' ? __('orders.mail.heading_placed') : $order->status->label() }}
    </h2>
    <p>{{ __('orders.mail.hello', ['name' => $order->name]) }}</p>
    <p>{{ $kind === 'placed' ? __('orders.mail.intro') : __('orders.mail.status_body.' . $order->status->value) }}</p>

    <p>{{ __('orders.show.reference') }}: <strong>{{ $order->reference }}</strong><br>
        {{ __('orders.show.type') }}: {{ $order->type->label() }}</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 480px;">
        <tr>
            <th align="left">{{ __('orders.mail.item') }}</th>
            <th>{{ __('orders.mail.qty') }}</th>
            <th align="right">{{ __('orders.mail.amount') }}</th>
        </tr>
        @foreach ($order->items as $line)
            <tr>
                <td>{{ $line->tr('name') }}@if ($line->optionsLabel(app()->getLocale()))
                        <br><small>{{ $line->optionsLabel(app()->getLocale()) }}</small>
                    @endif
                </td>
                <td align="center">{{ $line->quantity }}</td>
                <td align="right">{{ money($line->line_total) }}</td>
            </tr>
        @endforeach
        @if ((float) $order->delivery_fee > 0)
            <tr>
                <td colspan="2">{{ __('orders.delivery_fee') }}</td>
                <td align="right">{{ money($order->delivery_fee) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="2"><strong>{{ __('orders.total') }}</strong></td>
            <td align="right"><strong>{{ money($order->total) }}</strong></td>
        </tr>
    </table>

    <p><a href="{{ route('orders.show', $order->token) }}">{{ __('orders.mail.track') }}</a></p>
    <p>{{ setting('name', config('app.name')) }}</p>
</body>

</html>
