<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\PdfService;
use App\Support\Csv;
use App\Support\Exports;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    private const PDF_LIMIT = 1500;

    public function index()
    {
        return view('admin.downloads.index', ['datasets' => Exports::all()]);
    }

    public function download(Request $request, string $dataset, PdfService $pdf)
    {
        $all = Exports::all();
        abort_unless(isset($all[$dataset]), 404);
        $definition = $all[$dataset];

        $data = $request->validate([
            'format' => ['required', 'in:csv,pdf'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = ($definition['dated'] && filled($data['from'] ?? null)) ? Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay() : null;
        $to = ($definition['dated'] && filled($data['to'] ?? null)) ? Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay() : null;

        $period = $from || $to ? ($from?->format('d M Y') ?? 'start') . ' to ' . ($to?->format('d M Y') ?? 'today') : 'All time';
        $name = $dataset . '-' . now()->format('Ymd-His');

        ActivityLog::record('export.' . $dataset, "Downloaded {$definition['label']} as " . strtoupper($data['format']));

        if ($data['format'] === 'csv') {
            return response()->streamDownload(function () use ($definition, $from, $to) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // lets Excel read Bangla correctly
                fputcsv($out, $definition['headers'], ',', '"', '');

                foreach ($definition['rows']($from, $to) as $row) {
                    fputcsv($out, array_map([Csv::class, 'cell'], $row), ',', '"', '');
                }

                fclose($out);
            }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $rows = [];
        $truncated = false;

        foreach ($definition['rows']($from, $to) as $row) {
            if (count($rows) >= self::PDF_LIMIT) {
                $truncated = true;
                break;
            }

            $rows[] = array_map(fn($cell) => is_scalar($cell) || $cell === null ? (string) $cell : '', $row);
        }

        return $pdf->table([
            'title' => $definition['label'],
            'period' => $period,
            'headers' => $definition['headers'],
            'rows' => $rows,
            'truncated' => $truncated,
        ], count($definition['headers']) > 7)->download($name . '.pdf');
    }
}
