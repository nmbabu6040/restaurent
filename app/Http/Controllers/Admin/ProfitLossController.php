<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRangeRequest;
use App\Models\ActivityLog;
use App\Services\AnalyticsService;
use App\Support\Chart;
use App\Support\Csv;
use App\Support\ExpenseCategories;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitLossController extends Controller
{
    public function index(ReportRangeRequest $request, AnalyticsService $a)
    {
        $p = $request->period();
        $report = $a->profitLoss($p['from'], $p['to'], $p['group']);
        $series = $report['series'];
        $categories = ExpenseCategories::all();
        $labels = array_column($series, 'label');

        return view('admin.reports.profit', [
            'p' => $p,
            'presets' => AnalyticsService::PRESETS,
            's' => $report['summary'],
            'series' => $series,
            'expenseCategories' => $report['expense_categories'],
            'categoryNames' => $categories,
            'dishes' => $report['dishes'],
            'charts' => [
                'costs' => Chart::bar($labels, [
                    ['label' => 'Revenue', 'data' => array_column($series, 'revenue'), 'color' => '#6366F1'],
                    ['label' => 'Costs', 'data' => array_column($series, 'costs'), 'color' => '#F59E0B'],
                ], true),
                'profit' => Chart::bar($labels, [[
                    'label' => 'Profit',
                    'data' => array_column($series, 'profit'),
                    'colors' => array_map(fn($v) => $v >= 0 ? '#10B981' : '#EF4444', array_column($series, 'profit')),
                ]], true),
                'expenses' => Chart::doughnut(
                    array_map(fn($k) => $categories[$k] ?? $k, array_keys($report['expense_categories'])),
                    array_values($report['expense_categories']),
                    'Expenses',
                    [],
                    true,
                ),
            ],
        ]);
    }

    public function export(ReportRangeRequest $request, AnalyticsService $a): StreamedResponse
    {
        $p = $request->period();
        $report = $a->profitLoss($p['from'], $p['to'], $p['group']);

        ActivityLog::record('report.exported', "Exported profit and loss {$p['from']->toDateString()} to {$p['to']->toDateString()}");

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Period', 'Revenue', 'Costs', 'Profit'], ',', '"', '');

            foreach ($report['series'] as $row) {
                fputcsv($out, [Csv::cell($row['label']), $row['revenue'], $row['costs'], $row['profit']], ',', '"', '');
            }

            $s = $report['summary'];
            fputcsv($out, [], ',', '"', '');
            foreach (
                [
                    'Revenue' => $s['revenue'],
                    'Food cost' => $s['cogs'],
                    'Gross profit' => $s['gross'],
                    'Wastage' => $s['wastage'],
                    'Rider fees' => $s['courier'],
                    'Expenses' => $s['expenses'],
                    'Net profit' => $s['net'],
                ] as $label => $value
            ) {
                fputcsv($out, [$label, $value], ',', '"', '');
            }

            fclose($out);
        }, 'profit-loss-' . $p['from']->format('Ymd') . '-' . $p['to']->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
