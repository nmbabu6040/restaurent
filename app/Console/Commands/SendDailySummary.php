<?php

namespace App\Console\Commands;

use App\Mail\DailySalesSummaryMail;
use App\Models\Ingredient;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailySummary extends Command
{
    protected $signature = 'report:daily-summary {--date= : Day to report, Y-m-d (default: today)} {--to= : Send to this address instead of DAILY_REPORT_EMAIL}';

    protected $description = 'Email the owner a summary of the day\'s sales';

    public function handle(AnalyticsService $analytics): int
    {
        $to = $this->option('to') ?: config('restaurant.reports.daily_email');

        if (blank($to)) {
            $this->warn('No recipient. Set DAILY_REPORT_EMAIL in .env.');

            return self::SUCCESS;
        }

        try {
            $day = $this->option('date') ? Carbon::createFromFormat('!Y-m-d', (string) $this->option('date')) : now();
        } catch (\Throwable) {
            $this->error('Use the date format Y-m-d, for example 2026-11-15.');

            return self::FAILURE;
        }

        $from = $day->copy()->startOfDay();
        $until = $day->copy()->endOfDay();
        $yesterday = $from->copy()->subDay();

        Mail::to($to)->queue(new DailySalesSummaryMail(
            $day->format('d M Y'),
            $analytics->summary($from, $until),
            $analytics->summary($yesterday, $yesterday->copy()->endOfDay()),
            $analytics->topItems($from, $until, 5),
            $analytics->statusCounts($from, $until),
            $analytics->profitLoss($from, $until, 'daily', false)['summary'],
            Ingredient::low()->count(),
        ));

        $this->info("Summary for {$day->format('d M Y')} queued for {$to}.");

        return self::SUCCESS;
    }
}
