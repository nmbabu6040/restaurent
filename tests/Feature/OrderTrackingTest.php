<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_page_is_reachable_only_with_the_private_token(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.show', $order->token))->assertOk()->assertSee($order->reference);
        $this->get('/orders/' . $order->reference)->assertNotFound();
        $this->get('/orders/' . str_repeat('a', 40))->assertNotFound();
    }

    public function test_status_endpoint_returns_only_status_information(): void
    {
        $order = Order::factory()->create(['status' => 'preparing']);

        $this->getJson(route('orders.status', $order->token))
            ->assertOk()
            ->assertExactJson(['status' => 'preparing', 'label' => 'Preparing', 'final' => false, 'signature' => 'preparing|']);
    }

    public function test_lookup_by_reference_and_phone_in_any_format(): void
    {
        $order = Order::factory()->create(['phone' => '01712345678']);

        foreach (['01712345678', '+8801712345678', '017 1234 5678'] as $phone) {
            $this->post('/orders/track', ['reference' => strtolower($order->reference), 'phone' => $phone])
                ->assertRedirect(route('orders.show', $order->token));
        }
    }

    public function test_lookup_fails_with_a_wrong_phone_or_reference(): void
    {
        $order = Order::factory()->create(['phone' => '01712345678']);

        $this->post('/orders/track', ['reference' => $order->reference, 'phone' => '01799999999'])
            ->assertSessionHasErrors('reference');
        $this->post('/orders/track', ['reference' => 'MO-NOSUCH00', 'phone' => '01712345678'])
            ->assertSessionHasErrors('reference');
        $this->post('/orders/track', [])->assertSessionHasErrors(['reference', 'phone']);
    }

    public function test_lookup_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/orders/track', ['reference' => 'MO-NOSUCH00', 'phone' => '01700000000']);
        }

        $this->post('/orders/track', ['reference' => 'MO-NOSUCH00', 'phone' => '01700000000'])->assertStatus(429);
    }

    public function test_cancelled_order_page_explains_it(): void
    {
        $order = Order::factory()->cancelled()->create();

        $this->get(route('orders.show', $order->token))->assertOk()->assertSee('cancelled');
    }
}
