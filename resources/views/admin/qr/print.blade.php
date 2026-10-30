@php
    $brand = setting('name', config('app.name'));
    $logo = setting_image('logo_header');
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>QR sign</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        body {
            margin: 0;
            font-family: Georgia, serif;
            color: #172B4D;
            text-align: center;
        }

        .sheet {
            max-width: 150mm;
            margin: 0 auto;
            padding-top: 10mm;
        }

        .brand {
            font-size: 32px;
            font-weight: bold;
        }

        .brand img {
            max-height: 24mm;
            width: auto;
        }

        .code {
            width: 110mm;
            margin: 14mm auto 8mm;
        }

        .code svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .label {
            font-size: 28px;
            font-weight: bold;
        }

        .url {
            margin-top: 6mm;
            font: 14px monospace;
            color: #475569;
            word-break: break-all;
        }

        .no-print {
            margin: 12px 0;
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
    <div class="no-print"><button type="button" data-print style="font-size:14px;padding:6px 14px;">Print</button></div>
    <div class="sheet">
        <div class="brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $brand }}">
            @else
                {{ $brand }}
            @endif
        </div>
        <div class="code">{!! $svg !!}</div>
        <p class="label">{{ $label }}</p>
        <p class="url">{{ $url }}</p>
    </div>
</body>

</html>
