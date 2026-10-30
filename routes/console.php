<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire-unpaid')->everyTenMinutes();
Schedule::command('backup:run')->dailyAt(config('restaurant.backup.time'))->when(fn() => config('restaurant.backup.enabled'));
Schedule::command('report:daily-summary')->dailyAt(config('restaurant.reports.daily_time'));
