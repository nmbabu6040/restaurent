<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LanModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_is_forced_only_on_a_real_production_site(): void
    {
        $this->assertFalse(https_forced()); // the testing environment

        $this->app->detectEnvironment(fn() => 'production');

        config(['restaurant.lan_mode' => false]);
        $this->assertTrue(https_forced());

        config(['restaurant.lan_mode' => true]);
        $this->assertFalse(https_forced());
    }

    public function test_preflight_checks_http_instead_of_https_in_lan_mode(): void
    {
        config(['restaurant.lan_mode' => true, 'app.url' => 'http://192.168.1.10']);
        Artisan::call('app:preflight');
        $lan = Artisan::output();

        $this->assertStringContainsString('APP_URL is http on the local network (LAN mode)', $lan);
        $this->assertStringNotContainsString('APP_URL uses https', $lan);

        config(['restaurant.lan_mode' => false]);
        Artisan::call('app:preflight');
        $normal = Artisan::output();

        $this->assertStringContainsString('APP_URL uses https', $normal);
        $this->assertStringNotContainsString('(LAN mode)', $normal);
    }

    public function test_online_payment_is_flagged_in_lan_mode(): void
    {
        config(['restaurant.lan_mode' => true, 'restaurant.ordering.online_payment' => true]);
        Artisan::call('app:preflight');

        $this->assertStringContainsString('Online payment is off in LAN mode', Artisan::output());
    }
}
