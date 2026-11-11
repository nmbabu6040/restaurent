<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRangeRequest;
use App\Models\ActivityLog;
use App\Services\AnalyticsService;
use App\Support\Chart;
use App\Support\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffSalesController extends Controller
{
    public function index(ReportRangeRequest $request, AnalyticsService $a)
    {
        $p = $request->period();
        $rows = $a->staffSales($p['from'], $p['to']);

        return view('admin.reports.staff', [
            'p' => $p,
            'presets' => AnalyticsService::PRESETS,
            'rows' => $rows,
            'totals' => [
                'revenue' => array_sum(array_column($rows, 'revenue')),
                'bills' => array_sum(array_column($rows, 'bills')),
                'discount' => array_sum(array_column($rows, 'discount')),
                'cancelled' => array_sum(array_column($rows, 'cancelled')),
            ],
            'chart' => Chart::bar(
                array_column($rows, 'name'),
                [['label' => 'Sales', 'data' => Chart::round(array_column($rows, 'revenue')), 'color' => '#6366F1']],
                true,
                true,
            ),
        ]);
    }

    public function export(ReportRangeRequest $request, AnalyticsService $a): StreamedResponse
    {
        $p = $request->period();
        ActivityLog::record('report.exported', "Exported staff sales {$p['from']->toDateString()} to {$p['to']->toDateString()}");

        return response()->streamDownload(function () use ($a, $p) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Staff', 'Bills', 'Sales', 'Average bill', 'Items sold', 'Guests', 'Discounts given', 'Cancelled bills'], ',', '"', '');

            foreach ($a->staffSales($p['from'], $p['to']) as $r) {
                fputcsv($out, [Csv::cell($r['name']), $r['bills'], $r['revenue'], round($r['average'], 2), $r['items'], $r['guests'], $r['discount'], $r['cancelled']], ',', '"', '');
            }

            fclose($out);
        }, 'staff-sales-' . $p['from']->format('Ymd') . '-' . $p['to']->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
