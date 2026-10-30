@php $change = \App\Services\AnalyticsService::change($summary['revenue'], $previous['revenue']); @endphp
<!DOCTYPE html>
<html lang="en">

<body style="font-family: Arial, sans-serif; color: #292D32; line-height: 1.6;">
    <h2 style="color: #172B4D;">Sales summary for {{ $day }}</h2>

    <p style="font-size: 18px;"><strong>{{ money($summary['revenue']) }}</strong> from
        <strong>{{ $summary['orders'] }}</strong> {{ $summary['orders'] === 1 ? 'order' : 'orders' }}
        (average {{ money($summary['aov']) }}).
        @if ($change !== null)
            That is {{ $change >= 0 ? 'up' : 'down' }} {{ abs($change) }}% on the day before.
        @endif
    </p>

    <table style="border-collapse: collapse;">
        <tr>
            <td style="padding: 2px 16px 2px 0;">Portions sold</td>
            <td align="right">{{ $summary['items'] }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 16px 2px 0;">Delivery fees collected</td>
            <td align="right">{{ money($summary['delivery']) }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 16px 2px 0;">Discounts given</td>
            <td align="right">{{ money($summary['discount']) }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 16px 2px 0;">Food cost</td>
            <td align="right">{{ money($profit['cogs']) }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 16px 2px 0;"><strong>Estimated profit</strong></td>
            <td align="right"><strong>{{ money($profit['net']) }}</strong></td>
        </tr>
    </table>
    @if ($profit['uncosted'] > 0)
        <p style="font-size: 12px;">{{ $profit['uncosted'] }} order lines have no cost recorded, so the profit looks
            higher than it is.</p>
    @endif

    @if ($top)
        <h3 style="color: #172B4D;">Best sellers</h3>
        <ol>
            @foreach ($top as $dish)
                <li>{{ $dish['name'] }}: {{ $dish['qty'] }} sold ({{ money($dish['revenue']) }})</li>
            @endforeach
        </ol>
    @endif

    @if ($statuses)
        <h3 style="color: #172B4D;">Orders by status</h3>
        <p>
            @foreach ($statuses as $status => $count)
                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}@if (!$loop->last)
                    &middot;
                @endif
            @endforeach
        </p>
    @endif

    @if ($lowStock > 0)
        <p><strong>{{ $lowStock }}</strong> {{ $lowStock === 1 ? 'ingredient is' : 'ingredients are' }} low on
            stock.</p>
    @endif

    <p><a href="{{ route('admin.dashboard') }}">Open the dashboard</a></p>
</body>

</html>
