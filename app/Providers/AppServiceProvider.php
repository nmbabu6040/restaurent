<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('reservations', fn(Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('contact', fn(Request $r) => Limit::perMinutes(10, 3)->by($r->ip()));
        RateLimiter::for('newsletter', fn(Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
    }
}
