<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\MaintenanceMode::class,
        ]);
        $middleware->validateCsrfTokens(except: ['payments/sslcommerz/*']);
        // The admin dark-mode choice is a plain cookie written by JavaScript.
        $middleware->encryptCookies(except: ['admin_theme']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
