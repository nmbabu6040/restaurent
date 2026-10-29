<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRangeRequest;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\AnalyticsService;
use App\Support\Chart;
use App\Support\Csv;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    private const PAYMENT_LABELS = ['cod' => 'Cash', 'sslcommerz' => 'Online'];

    public function index(ReportRangeRequest $request, AnalyticsService $a)
    {
        $p = $request->period();

        $summary = $a->summary($p['from'], $p['to']);
        $previous = $a->summary($p['prev_from'], $p['prev_to']);
        $series = $a->series($p['from'], $p['to'], $p['group']);
        $breakdown = $a->breakdown($p['from'], $p['to']);
        $top = $a->topItems($p['from'], $p['to'], 10);
        $hours = $a->hourly($p['from'], $p['to']);

        $paymentKeys = array_keys($breakdown['payment']);

        return view('admin.reports.sales', [
            'p' => $p,
            'presets' => AnalyticsService::PRESETS,
            'summary' => $summary,
            'change' => [
                'revenue' => AnalyticsService::change($summary['revenue'], $previous['revenue']),
                'orders' => AnalyticsService::change($summary['orders'], $previous['orders']),
                'aov' => AnalyticsService::change($summary['aov'], $previous['aov']),
            ],
            'series' => $series,
            'topItems' => $top,
            'charts' => [
                'revenue' => Chart::bar(array_column($series, 'label'), [['label' => 'Revenue', 'data' => Chart::round(array_column($series, 'revenue')), 'color' => '#6366F1']], true),
                'orders' => Chart::line(array_column($series, 'label'), [['label' => 'Orders', 'data' => array_column($series, 'orders'), 'color' => '#10B981']]),
                'type' => Chart::doughnut(
                    array_map('ucfirst', array_keys($breakdown['type'])),
                    array_column($breakdown['type'], 'orders'),
                    'Orders',
                    ['#F59E0B', '#06B6D4', '#8B5CF6'],
                ),
                'payment' => Chart::doughnut(
                    array_map(fn($k) => self::PAYMENT_LABELS[$k] ?? $k, $paymentKeys),
                    Chart::round(array_column($breakdown['payment'], 'revenue')),
                    'Revenue',
                    ['#10B981', '#6366F1'],
                    true,
                ),
                'top' => Chart::bar(array_column($top, 'name'), [['label' => 'Portions sold', 'data' => array_column($top, 'qty'), 'color' => '#F59E0B']], false, true),
                'hours' => Chart::bar(
                    array_map(fn($h) => Carbon::createFromTime($h)->format('g A'), array_keys($hours)),
                    [['label' => 'Orders', 'data' => $hours, 'color' => '#EC4899']],
                ),
            ],
        ]);
    }

    public function export(ReportRangeRequest $request, AnalyticsService $a): StreamedResponse
    {
        $p = $request->period();

        ActivityLog::record('report.exported', "Exported sales {$p['from']->toDateString()} to {$p['to']->toDateString()}");

        return response()->streamDownload(function () use ($a, $p) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // lets Excel read Bangla names correctly
            fputcsv($out, ['Reference', 'Date', 'Type', 'Status', 'Payment method', 'Payment status', 'Subtotal', 'Delivery fee', 'Discount', 'Total', 'Coupon', 'Customer', 'Phone'], ',', '"', '');

            $a->salesWhere(Order::query())
                ->whereBetween('orders.created_at', [$p['from'], $p['to']])
                ->chunkById(500, function ($orders) use ($out) {
                    foreach ($orders as $o) {
                        fputcsv($out, [
                            $o->reference,
                            $o->created_at->format('Y-m-d H:i'),
                            $o->type->value,
                            $o->status->value,
                            $o->payment_method,
                            $o->payment_status,
                            $o->subtotal,
                            $o->delivery_fee,
                            $o->discount,
                            $o->total,
                            Csv::cell($o->coupon_code),
                            Csv::cell($o->name),
                            Csv::cell($o->phone),
                        ], ',', '"', '');
                    }
                }, 'orders.id', 'id');

            fclose($out);
        }, 'sales-' . $p['from']->format('Ymd') . '-' . $p['to']->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
