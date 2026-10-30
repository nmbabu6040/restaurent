<?php

namespace App\Console\Commands;

use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Console\Command;
use App\Services\BackupService;
use Illuminate\Support\Facades\DB;

class Preflight extends Command
{
    protected $signature = 'app:preflight';

    protected $description = 'Check the production configuration before going live';

    public function handle(): int
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $online = (bool) config('restaurant.ordering.online_payment');

        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        // Runs a database check; any error counts as "no".
        $db = function (callable $check) use ($dbOk): bool {
            if (! $dbOk) {
                return false;
            }

            try {
                return (bool) $check();
            } catch (\Throwable) {
                return false;
            }
        };

        $isPlaceholder = fn(?string $v) => blank($v)
            || (bool) preg_match('/XXX|Your address here|@meher\.test|example\.(com|org)/i', (string) $v);

        $placeholders = $db(fn() => $isPlaceholder(setting('phone'))
            || $isPlaceholder(setting('email'))
            || $isPlaceholder(setting('address')));

        $sampleContent = $db(fn() => Testimonial::where('name', 'like', 'Sample %')->exists()
            || TeamMember::where('name', 'like', 'Sample %')->exists());

        // [label, passed, how to fix]
        $required = [
            [
                'APP_ENV is production',
                app()->isProduction(),
                'Set APP_ENV=production'
            ],
            [
                'APP_DEBUG is off',
                ! config('app.debug'),
                'Set APP_DEBUG=false'
            ],
            [
                'APP_KEY is set',
                filled(config('app.key')),
                'Run php artisan key:generate --force (only on a new install)'
            ],
            [
                'APP_URL uses https',
                str_starts_with($url, 'https://'),
                'Set APP_URL=https://your-domain'
            ],
            [
                'APP_URL is not a local address',
                ! preg_match('/localhost|127\.0\.0\.1|\.test$/', $host),
                'Set APP_URL to your real domain'
            ],
            [
                'Session cookie is HTTPS-only',
                config('session.secure') === true,
                'Set SESSION_SECURE_COOKIE=true'
            ],
            [
                'Mail sends for real',
                ! in_array(config('mail.default'), ['log', 'array'], true),
                'Configure SMTP in .env (MAIL_MAILER=smtp ...)'
            ],
            [
                'Database is reachable',
                $dbOk,
                'Check DB_* in .env and run php artisan migrate --force'
            ],
            [
                'storage and bootstrap/cache are writable',
                is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
                'Fix folder permissions (docs/DEPLOYMENT.md)'
            ],
            [
                'public/storage link exists',
                file_exists(public_path('storage')),
                'Run php artisan storage:link'
            ],
            [
                'Front-end build exists',
                file_exists(public_path('build/manifest.json')),
                'Run npm run build'
            ],
            [
                'No Vite dev-server file (public/hot)',
                ! file_exists(public_path('hot')),
                'Delete public/hot'
            ],
            [
                'No static public/robots.txt',
                ! file_exists(public_path('robots.txt')),
                'Delete public/robots.txt (the site generates one)'
            ],
            [
                'An active admin exists',
                $db(fn() => User::where('role', 'admin')->where('is_active', true)->exists()),
                'Run php artisan db:seed --class=AdminSeeder --force'
            ],
            [
                'No admin uses the sample @meher.test email',
                $db(fn() => ! User::where('email', 'like', '%@meher.test')->exists()),
                'Create a real admin, then delete the sample one'
            ],
            [
                'Phone, email and address are real',
                $dbOk && ! $placeholders,
                'Edit them in Admin > Settings'
            ],
        ];

        if ($online) {
            $required[] = ['Online payment: live (not sandbox) keys', ! config('services.sslcommerz.sandbox')
                && filled(config('services.sslcommerz.store_id'))
                && filled(config('services.sslcommerz.store_password')), 'Set SSLCOMMERZ_SANDBOX=false and the live store id and password'];
        }

        $recommended = [
            ['A backup was made in the last 3 days', $dbOk && collect(app(BackupService::class)->list())->contains(fn($f) => $f['time']->gt(now()->subDays(3))), 'Run php artisan backup:run, and make sure the scheduler cron job is set (docs/DEPLOYMENT.md)'],
            ['Configuration is cached', app()->configurationIsCached(), 'Run php artisan optimize'],
            ['Routes are cached', app()->routesAreCached(), 'Run php artisan optimize'],
            ['Queue is not "sync"', config('queue.default') !== 'sync', 'Set QUEUE_CONNECTION=database and run a worker'],
            ['Cache store is not "array"', config('cache.default') !== 'array', 'Set CACHE_STORE=database'],
            ['Session data is encrypted', (bool) config('session.encrypt'), 'Set SESSION_ENCRYPT=true'],
            ['Content-Security-Policy is enforced', config('security.csp') === 'enforce', 'Set SECURITY_CSP=enforce'],
            ['Sample testimonials and team removed', $dbOk && ! $sampleContent, 'Delete or edit them in Admin > Testimonials / Team'],
        ];

        $rows = [];
        $failed = 0;

        foreach ($required as [$label, $ok, $fix]) {
            $rows[] = [$ok ? 'OK' : 'FAIL', $label, $ok ? '' : $fix];
            $failed += $ok ? 0 : 1;
        }

        foreach ($recommended as [$label, $ok, $fix]) {
            $rows[] = [$ok ? 'OK' : 'WARN', $label, $ok ? '' : $fix];
        }

        $this->table(['', 'Check', 'How to fix'], $rows);

        if ($failed > 0) {
            $this->error("{$failed} required check(s) failed. Do not go live yet.");

            return self::FAILURE;
        }

        $this->info('All required checks passed.');

        return self::SUCCESS;
    }
}
