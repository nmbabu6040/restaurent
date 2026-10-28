<!DOCTYPE html>
<html lang="en">

<body style="font-family: Arial, sans-serif; color: #292D32; line-height: 1.6;">
    <h2 style="color: #172B4D;">New order {{ $order->reference }}</h2>
    <p>
        <strong>{{ ucfirst($order->type->value) }}</strong> &middot;
        {{ $order->payment_method === 'cod' ? 'Cash' : 'Paid online' }} &middot;
        <strong>{{ money($order->total) }}</strong>
    </p>
    <p>
        {{ $order->name }}, {{ $order->phone }}<br>
        @if ($order->address)
            {{ $order->address }}<br>
        @endif
        @if ($order->notes)
            Notes: {{ $order->notes }}
        @endif
    </p>
    <ul>
        @foreach ($order->items as $line)
            <li>{{ $line->quantity }} &times; {{ $line->name_en }}</li>
        @endforeach
    </ul>
    <p><a href="{{ route('admin.orders.show', $order) }}">Open in admin</a></p>
</body>

</html>
