<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Kitchen ticket {{ $order->reference }}</title>
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
            margin: 6px 0 0;
        }

        .opt {
            padding-left: 12px;
            font-size: 11px;
            margin: 0;
        }

        .rule {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .badge {
            display: inline-block;
            border: 2px solid #000;
            padding: 1px 8px;
            font-weight: bold;
        }

        .note {
            border: 2px solid #000;
            padding: 4px;
            margin-top: 8px;
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

<body data-autoprint="1">
    <div class="no-print"><button type="button" data-print style="font-size:14px;padding:6px 14px;">Print</button></div>

    <p class="center big">KITCHEN ORDER{{ $reprint ? ' (REPRINT)' : '' }}</p>
    <p class="center"><span class="badge">{{ $order->table_name ?: 'TAKEAWAY' }}</span></p>
    <div class="rule"></div>
    <p>{{ $order->reference }} &middot; {{ now()->format('g:i A') }}@if ($order->guests)
            &middot; {{ $order->guests }} guests
        @endif
    </p>
    @if ($order->cashier)
        <p>Waiter: {{ $order->cashier->name }}</p>
    @endif
    <div class="rule"></div>

    @foreach ($rows as $row)
        <p class="huge">{{ $row['qty'] }} x {{ $row['line']->name_en }}</p>
        @if ($row['line']->optionsLabel('en'))
            <p class="opt">{{ $row['line']->optionsLabel('en') }}</p>
        @endif
    @endforeach

    @if ($order->notes)
        <div class="note">NOTE: {{ $order->notes }}</div>
    @endif
</body>

</html>
