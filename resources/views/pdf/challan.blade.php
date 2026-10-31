<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #222;
        }

        h1 {
            font-size: 24px;
            margin: 0;
            color: #172B4D;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            padding: 6px 5px;
            text-align: left;
            vertical-align: top;
        }

        .r {
            text-align: right;
        }

        .muted {
            color: #666;
        }

        .items th {
            background: #172B4D;
            color: #fff;
        }

        .items td {
            border-bottom: 1px solid #ddd;
        }

        .box {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .sign td {
            padding-top: 46px;
            border-top: 1px solid #333;
            text-align: center;
            width: 30%;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <td>
                <h1>{{ setting_en('name', config('app.name')) }}</h1>
                <div class="muted">{{ setting_en('address') }}<br>{{ setting_en('phone') }}@if (setting_en('invoice_bin'))
                        | BIN: {{ setting_en('invoice_bin') }}
                    @endif
                </div>
            </td>
            <td class="r">
                <h1>DELIVERY CHALLAN</h1>
                <div>Challan no: <strong>CH-{{ $order->reference }}</strong></div>
                <div>Order: {{ $order->reference }}</div>
                <div>Date: {{ $order->created_at->format('d M Y, g:i A') }}</div>
            </td>
        </tr>
    </table>
    <br>
    <table>
        <tr>
            <td class="box" style="width:50%"><strong>Deliver to</strong><br>{{ $order->name }}<br>{{ $order->phone }}
                @if ($order->address)
                    <br>{{ $order->address }}
                @endif
                @if ($order->zone_en)
                    <br>Area: {{ $order->zone_en }}
                @endif
            </td>
            <td class="box" style="width:50%"><strong>Delivery</strong><br>Type:
                {{ ucfirst($order->type->value) }}<br>
                @if ($order->delivery?->rider)
                    Rider: {{ $order->delivery->rider->name }} ({{ $order->delivery->rider->phone }})<br>
                @endif
                @if ($order->payment_method === 'cod' && $order->payment_status !== 'paid')
                    <strong>Collect cash: {{ pdf_money($order->total) }}</strong>
                @else
                    Payment: received
                @endif
            </td>
        </tr>
    </table>
    <br>
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th class="r">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->name_en }}@if ($line->optionsLabel('en'))
                            <span class="muted">({{ $line->optionsLabel('en') }})</span>
                        @endif
                    </td>
                    <td class="r">{{ $line->quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p><strong>Total portions: {{ $order->items->sum('quantity') }}</strong></p>
    @if ($order->notes)
        <p class="box"><strong>Note:</strong> {{ $order->notes }}</p>
    @endif
    <p class="muted">Please check the goods at delivery. Report any shortage or damage to the rider straight away.</p>

    <table class="sign" style="margin-top: 20px;">
        <tr>
            <td>Prepared by</td>
            <td style="border-top: 0; width: 5%;"></td>
            <td>Delivered by (rider)</td>
            <td style="border-top: 0; width: 5%;"></td>
            <td>Received by (customer)</td>
        </tr>
    </table>
</body>

</html>
