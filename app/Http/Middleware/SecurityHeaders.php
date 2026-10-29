<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** Pages that hold personal data or that search engines should not list. */
    private const PRIVATE_PATHS = ['admin', 'admin/*', 'cart', 'checkout', 'orders/*', 'reservations/*', 'payments/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // The Vite dev server needs inline scripts and websockets, so the policy is skipped while it runs.
        $mode = config('security.csp');

        if ($mode !== 'off' && ! Vite::isRunningHot()) {
            $headers->set(
                $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
                $this->policy($request),
            );
        }

        if ($request->is(...self::PRIVATE_PATHS)) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function policy(Request $request): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",   // inline styles only (error page); scripts stay strict
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            'frame-src https://www.google.com',   // Google Maps embed on the contact page
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        // form-action is left out on purpose: the "Pay online" form redirects to the SSLCommerz page.
        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
