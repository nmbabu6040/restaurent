<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactNewsletterTest extends TestCase
{
    use RefreshDatabase;

    private function message(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Do you cater for events?',
        ], $overrides);
    }

    public function test_contact_message_is_saved(): void
    {
        $this->postJson('/contact', $this->message(['phone' => '01712345678', 'subject' => 'Events']))
            ->assertCreated()->assertJsonStructure(['message']);

        $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com', 'subject' => 'Events']);
        $this->assertNotNull(\App\Models\ContactMessage::first()->ip_address);
    }

    public function test_contact_validation(): void
    {
        $this->postJson('/contact', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->postJson('/contact', $this->message(['email' => 'nope']))->assertJsonValidationErrors('email');
        $this->postJson('/contact', $this->message(['message' => str_repeat('x', 2001)]))->assertJsonValidationErrors('message');
        $this->postJson('/contact', $this->message(['phone' => 'abc']))->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_honeypot_and_rate_limit(): void
    {
        $this->postJson('/contact', $this->message(['website' => 'http://spam.example']))
            ->assertStatus(422)->assertJsonValidationErrors('website');

        // The honeypot hit above already counted, so two more are allowed before the limit (3 per 10 minutes).
        $this->postJson('/contact', $this->message())->assertCreated();
        $this->postJson('/contact', $this->message())->assertCreated();
        $this->postJson('/contact', $this->message())->assertStatus(429);

        $this->assertDatabaseCount('contact_messages', 2);
    }

    public function test_newsletter_subscription_is_idempotent_and_gives_nothing_away(): void
    {
        $first = $this->postJson('/newsletter', ['email' => 'Fan@Example.com'])->assertCreated()->json('message');
        $second = $this->postJson('/newsletter', ['email' => 'fan@example.com'])->assertCreated()->json('message');

        $this->assertSame($first, $second);
        $this->assertSame(1, NewsletterSubscriber::count());
        $this->assertSame('fan@example.com', NewsletterSubscriber::first()->email);
    }

    public function test_newsletter_validation_and_honeypot(): void
    {
        $this->postJson('/newsletter', ['email' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/newsletter', ['email' => 'a@example.com', 'website' => 'spam'])->assertStatus(422);

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_unsubscribe_and_resubscribe(): void
    {
        $this->postJson('/newsletter', ['email' => 'fan@example.com']);
        $subscriber = NewsletterSubscriber::first();

        $this->get(route('newsletter.unsubscribe', $subscriber->token))->assertRedirect(route('home'));
        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);

        $this->postJson('/newsletter', ['email' => 'fan@example.com'])->assertCreated();
        $this->assertNull($subscriber->fresh()->unsubscribed_at);
        $this->assertSame(1, NewsletterSubscriber::count());

        $this->get(route('newsletter.unsubscribe', 'not-a-real-token'))->assertRedirect(route('home'));
    }

    public function test_newsletter_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/newsletter', ['email' => "fan{$i}@example.com"])->assertCreated();
        }

        $this->postJson('/newsletter', ['email' => 'fan6@example.com'])->assertStatus(429);
    }
}
