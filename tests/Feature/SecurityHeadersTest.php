<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_content_security_policy_is_sent_and_can_be_switched_off(): void
    {
        if (Vite::isRunningHot()) {
            $this->markTestSkipped('The Vite dev server is running (public/hot exists); CSP is skipped on purpose.');
        }

        $csp = $this->get('/')->assertHeader('Content-Security-Policy')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);

        config(['security.csp' => 'off']);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy');

        config(['security.csp' => 'report-only']);
        $this->get('/')->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_private_pages_are_not_cached_or_indexed(): void
    {
        foreach (['/admin/login', '/cart'] as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_public_pages_are_cacheable_and_indexable(): void
    {
        $response = $this->get('/');

        $response->assertHeaderMissing('X-Robots-Tag');
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
