@php
    $isKot = $type === 'kot';
    $brand = setting('name', config('app.name'));
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $isKot ? 'Kitchen ticket' : 'Receipt' }} {{ $order->reference }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0 auto;
            width: 74mm;
            padding: 2mm;
            font: 12px/1.35 'Courier New', monospace;
            color: #000;
            background: #fff;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        .center {
            text-align: center;
        }

        .big {
            font-size: 16px;
            font-weight: bold;
        }

        .huge {
            font-size: 20px;
            font-weight: bold;
            margin-top: 6px;
        }

        .rule {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .bold {
            font-weight: bold;
        }

        .opt {
            padding-left: 12px;
            font-size: 11px;
        }

        .note {
            border: 2px solid #000;
            padding: 4px;
            margin-top: 8px;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            border: 2px solid #000;
            padding: 1px 8px;
            font-weight: bold;
        }

        .no-print {
            margin: 8px 0;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
    @vite(['resources/js/app.js'])
</head>

<body data-autoprint="{{ $autoprint ? '1' : '0' }}">
    <div class="no-print">
        <button type="button" data-print style="font-size:14px;padding:6px 14px;">Print</button>

    </div>

    @if ($isKot)
        <p class="center big">KITCHEN ORDER</p>
        <p class="center"><span class="badge">{{ strtoupper($order->type->value) }}</span></p>
        <div class="rule"></div>
        <div class="row bold"><span>{{ $order->reference }}</span><span>{{ $order->created_at->format('g:i A') }}</span>
        </div>
        <div>{{ $order->created_at->format('d M Y') }} &middot; {{ $order->name }}</div>
        <div class="rule"></div>

        @foreach ($order->items as $line)
            <p class="huge">{{ $line->quantity }} x {{ $line->name_en }}</p>
            @if ($line->optionsLabel('en'))
                <p class="opt">{{ $line->optionsLabel('en') }}</p>
            @endif
        @endforeach

        @if ($order->notes)
            <div class="note">NOTE: {{ $order->notes }}</div>
        @endif
        <div class="rule"></div>
        <p class="center">{{ $order->items->sum('quantity') }} portions</p>
    @else
        <p class="center big">{{ $brand }}</p>
        @if (setting('address'))
            <p class="center">{{ setting('address') }}</p>
        @endif
        @if (setting('phone'))
            <p class="center">{{ setting('phone') }}</p>
        @endif
        <div class="rule"></div>
        <div class="row"><span>Order</span><span class="bold">{{ $order->reference }}</span></div>
        <div class="row"><span>Date</span><span>{{ $order->created_at->format('d M Y, g:i A') }}</span></div>
        <div class="row"><span>Type</span><span>{{ ucfirst($order->type->value) }}</span></div>
        <div class="row"><span>Customer</span><span>{{ $order->name }}</span></div>
        @if ($order->type->value === 'delivery')
            <div class="row"><span>Phone</span><span>{{ $order->phone }}</span></div>
            @if ($order->address)
                <p>{{ $order->address }}</p>
            @endif
            @if ($order->zone_en)
                <p>Area: {{ $order->zone_en }}</p>
            @endif
        @endif
        <div class="rule"></div>

        @foreach ($order->items as $line)
            <div class="row"><span>{{ $line->quantity }} x
                    {{ $line->name_en }}</span><span>{{ money($line->line_total) }}</span></div>
            @if ($line->optionsLabel('en'))
                <p class="opt">{{ $line->optionsLabel('en') }}</p>
            @endif
            @if ($line->quantity > 1)
                <p class="opt">@ {{ money($line->unit_price) }} each</p>
            @endif
        @endforeach

        <div class="rule"></div>
        <div class="row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
        @if ((float) $order->discount > 0)
            <div class="row">
                <span>Discount{{ $order->coupon_code ? ' (' . $order->coupon_code . ')' : '' }}</span><span>-{{ money($order->discount) }}</span>
            </div>
        @endif
        @if ((float) $order->delivery_fee > 0)
            <div class="row"><span>Delivery</span><span>{{ money($order->delivery_fee) }}</span></div>
        @endif
        <div class="row big"><span>TOTAL</span><span>{{ money($order->total) }}</span></div>
        <div class="rule"></div>
        <div class="row"><span>Payment</span><span>{{ $order->payment_method === 'cod' ? 'Cash' : 'Online' }},
                {{ $order->payment_status }}</span></div>
        <div class="rule"></div>
        <p class="center">Thank you!</p>
    @endif
</body>

</html>
