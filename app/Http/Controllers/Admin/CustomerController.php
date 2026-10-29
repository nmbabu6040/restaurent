<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRangeRequest;
use App\Models\ActivityLog;
use App\Services\AnalyticsService;
use App\Support\Chart;
use App\Support\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(ReportRangeRequest $request, AnalyticsService $a)
    {
        $p = $request->period();
        $report = $a->customerReport($p['from'], $p['to'], $p['group']);
        $series = $report['series'];

        return view('admin.reports.customers', [
            'p' => $p,
            'presets' => AnalyticsService::PRESETS,
            'summary' => $report['summary'],
            'top' => $report['top'],
            'chart' => Chart::bar(
                array_column($series, 'label'),
                [
                    ['label' => 'New customers', 'data' => array_column($series, 'new'), 'color' => '#EC4899'],
                    ['label' => 'Returning customers', 'data' => array_column($series, 'returning'), 'color' => '#6366F1'],
                ],
                false,
                false,
                true,
            ),
        ]);
    }

    public function export(AnalyticsService $a): StreamedResponse
    {
        ActivityLog::record('report.exported', 'Exported customer list');

        $customers = $a->allCustomers();
        usort($customers, fn($x, $y) => $y['spent'] <=> $x['spent']);

        return response()->streamDownload(function () use ($customers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Phone', 'Orders', 'Total spent', 'First order', 'Last order'], ',', '"', '');

            foreach ($customers as $c) {
                fputcsv($out, [
                    Csv::cell($c['name']),
                    Csv::cell($c['phone']),
                    $c['orders'],
                    number_format($c['spent'], 2, '.', ''),
                    $c['first']->format('Y-m-d'),
                    $c['last']->format('Y-m-d'),
                ], ',', '"', '');
            }

            fclose($out);
        }, 'customers-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
