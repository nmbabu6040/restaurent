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

        .totals td {
            padding: 3px 5px;
        }

        .grand td {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #172B4D;
        }

        .box {
            border: 1px solid #ddd;
            padding: 8px;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <td>
                <h1>{{ setting_en('name', config('app.name')) }}</h1>
                <div class="muted">
                    {{ setting_en('address') }}<br>
                    {{ setting_en('phone') }} {{ setting_en('email') ? ' | ' . setting_en('email') : '' }}
                    @if (setting_en('invoice_bin'))
                        <br>BIN: {{ setting_en('invoice_bin') }}
                    @endif
                </div>
            </td>
            <td class="r">
                <h1>INVOICE</h1>
                <div>No: <strong>{{ $order->reference }}</strong></div>
                <div>Date: {{ $order->created_at->format('d M Y, g:i A') }}</div>
            </td>
        </tr>
    </table>

    <br>
    <table>
        <tr>
            <td class="box" style="width:50%">
                <strong>Bill to</strong><br>
                {{ $order->name }}<br>{{ $order->phone }}
                @if ($order->address)
                    <br>{{ $order->address }}
                @endif
                @if ($order->zone_en)
                    <br>Area: {{ $order->zone_en }}
                @endif
            </td>
            <td class="box" style="width:50%">
                <strong>Order</strong><br>
                Type: {{ ucfirst($order->type->value) }}<br>
                Payment: {{ $order->payment_method === 'cod' ? 'Cash' : 'Online' }} ({{ $order->payment_status }})<br>
                Status: {{ ucfirst(str_replace('_', ' ', $order->status->value)) }}
            </td>
        </tr>
    </table>

    <br>
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th class="r">Qty</th>
                <th class="r">Unit price</th>
                <th class="r">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->name_en }}@if ($line->optionsLabel('en'))
                            <br><span class="muted">{{ $line->optionsLabel('en') }}</span>
                        @endif
                    </td>
                    <td class="r">{{ $line->quantity }}</td>
                    <td class="r">{{ pdf_money($line->unit_price) }}</td>
                    <td class="r">{{ pdf_money($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 45%; margin-left: 55%; margin-top: 8px;">
        <tr>
            <td>Subtotal</td>
            <td class="r">{{ pdf_money($order->subtotal) }}</td>
        </tr>
        @if ((float) $order->discount > 0)
            <tr>
                <td>Discount{{ $order->coupon_code ? ' (' . $order->coupon_code . ')' : '' }}</td>
                <td class="r">- {{ pdf_money($order->discount) }}</td>
            </tr>
        @endif
        @if ((float) $order->delivery_fee > 0)
            <tr>
                <td>Delivery fee</td>
                <td class="r">{{ pdf_money($order->delivery_fee) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="r">{{ pdf_money($order->total) }}</td>
        </tr>
        <tr>
            <td>Paid</td>
            <td class="r">{{ $order->payment_status === 'paid' ? pdf_money($order->total) : pdf_money(0) }}</td>
        </tr>
        @if ($order->payment_status !== 'paid')
            <tr>
                <td><strong>Due</strong></td>
                <td class="r"><strong>{{ pdf_money($order->total) }}</strong></td>
            </tr>
        @endif
    </table>

    @if ($order->notes)
        <p class="muted">Customer note: {{ $order->notes }}</p>
    @endif
    <p class="muted" style="margin-top: 24px;">{{ setting_en('invoice_note', 'Thank you for your order.') }}</p>
</body>

</html>
