<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        RateLimiter::for('orders', fn(Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('order-track', fn(Request $r) => Limit::perMinutes(5, 5)->by($r->ip()));
        RateLimiter::for('payments', fn(Request $r) => Limit::perMinutes(10, 10)->by($r->ip()));

        $is = fn(User $u, UserRole ...$roles) => $u->is_active && in_array($u->role, $roles, true);

        Gate::define('access-admin', fn(User $u) => $u->is_active && $u->role instanceof UserRole);
        Gate::define('handle-guests', fn(User $u) => $is($u, UserRole::Admin, UserRole::Manager, UserRole::Staff));
        Gate::define('manage-menu', fn(User $u) => $is($u, UserRole::Admin, UserRole::Manager));
        Gate::define('manage-content', fn(User $u) => $is($u, UserRole::Admin, UserRole::Manager));
        Gate::define('manage-settings', fn(User $u) => $is($u, UserRole::Admin));
        Gate::define('manage-users', fn(User $u) => $is($u, UserRole::Admin));
        Gate::define('view-logs', fn(User $u) => $is($u, UserRole::Admin));
    }
}
