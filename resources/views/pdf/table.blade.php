<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #222;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 2px;
            color: #172B4D;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th {
            background: #172B4D;
            color: #fff;
            text-align: left;
            padding: 4px;
        }

        td {
            padding: 3px 4px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        .muted {
            color: #666;
        }
    </style>
</head>

<body>
    <h1>{{ $title }}</h1>
    <div class="muted">{{ setting_en('name', config('app.name')) }} | {{ $period }} | Created
        {{ now()->format('d M Y, g:i A') }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($truncated)
        <p class="muted">Only the first {{ number_format(count($rows)) }} rows are shown. Use the CSV download for
            everything.</p>
    @endif
</body>

</html>
