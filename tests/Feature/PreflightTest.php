<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_fails_when_the_app_is_not_production_ready(): void
    {
        // The test environment is "testing", with debug-style settings, so the checks must fail.
        $this->artisan('app:preflight')->assertExitCode(1);
    }
}
