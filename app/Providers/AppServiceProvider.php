<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Reset for every request and every queued job, so a long-running queue worker never uses stale settings.
        $this->app->scoped('settings.memo', fn() => new \ArrayObject());
    }

    public function boot(): void
    {
        // Build every absolute URL (emails, sitemap, canonical, payment callbacks) from APP_URL,
        // so a forged Host header cannot inject another domain into them.
        if ($this->app->isProduction()) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }

        Password::defaults(fn() => Password::min(10)->letters()->numbers());

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
