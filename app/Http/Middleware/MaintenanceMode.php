<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isOn() || $this->isExempt($request)) {
            return $next($request);
        }

        return response()->view('maintenance', [], 503, [
            'Retry-After' => '3600',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function isOn(): bool
    {
        try {
            return setting('maintenance_enabled') === '1';
        } catch (\Throwable) {
            return false; // e.g. migrations have not run yet
        }
    }

    private function isExempt(Request $request): bool
    {
        // Payment confirmations must still reach us, and staff must be able to log in and switch the mode off.
        return $request->is('admin', 'admin/*', 'rider', 'rider/*', 'lang/*', 'payments/sslcommerz/*', 'up');
    }
}
