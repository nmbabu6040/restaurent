<?php

namespace Tests\Feature;

use App\Mail\NewOrderAlertMail;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;


class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'restaurant.ordering.online_payment' => true,
            'services.sslcommerz.store_id' => 'teststore',
            'services.sslcommerz.store_password' => 'testsecret',
            'services.sslcommerz.sandbox' => true,
        ]);
    }

    /** @return array{0: Order, 1: Payment} */
    private function onlineOrder(array $attributes = []): array
    {
        $order = Order::factory()->online()->create($attributes);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'sslcommerz',
            'tran_id' => $order->reference . '-AB12',
            'amount' => $order->total,
            'currency' => 'BDT',
            'status' => 'initiated',
        ]);

        return [$order, $payment];
    }

    private function fakeValidation(Payment $payment, array $overrides = []): void
    {
        Http::fake(['*validationserverAPI.php*' => Http::response(array_merge([
            'status' => 'VALID',
            'tran_id' => $payment->tran_id,
            'val_id' => 'VAL123',
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency_type' => 'BDT',
            'bank_tran_id' => 'BANK123',
            'card_type' => 'VISA-Dutch Bangla',
        ], $overrides))]);
    }

    private function sendPaymentCallback(
        Payment $payment,
        string $path = 'success'
    ): TestResponse {
        return $this->post("/payments/sslcommerz/{$path}", [
            'val_id' => 'VAL123',
            'tran_id' => $payment->tran_id,
            'status' => 'VALID',
        ]);
    }

    // Starting a payment

    public function test_payment_starts_with_the_server_side_amount(): void
    {
        [$order] = $this->onlineOrder();
        Http::fake(['*gwprocess/v4/api.php' => Http::response([
            'status' => 'SUCCESS',
            'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gw/abc123',
        ])]);

        $this->post(route('payments.start', $order->token), ['total_amount' => '1.00'])
            ->assertRedirect('https://sandbox.sslcommerz.com/gw/abc123');

        Http::assertSent(fn($r) => str_contains($r->url(), '/gwprocess/v4/api.php')
            && $r['total_amount'] === '440.00'
            && $r['currency'] === 'BDT'
            && $r['store_id'] === 'teststore'
            && str_starts_with($r['tran_id'], $order->reference . '-')
            && $r['success_url'] === route('payments.success')
            && $r['ipn_url'] === route('payments.ipn'));

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'initiated', 'amount' => '440.00']);
    }

    public function test_gateway_failures_do_not_break_the_order_page(): void
    {
        $order = Order::factory()->online()->create();

        foreach (
            [
                'rejected' => Http::response(['status' => 'FAILED', 'failedreason' => 'Invalid Information']),
                'foreign host' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://evil.example/pay']),
                'plain http' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'http://sandbox.sslcommerz.com/pay']),
                'server error' => Http::response('', 500),
                'unreachable' => fn() => throw new ConnectionException('boom'),
            ] as $label => $reply
        ) {
            Http::fake(['*' => $reply]);

            $this->post(route('payments.start', $order->token))
                ->assertRedirect(route('orders.show', $order->token))
                ->assertSessionHas('error');
        }

        $this->assertSame(0, Payment::where('status', 'paid')->count());
        $this->assertSame(5, Payment::where('status', 'failed')->count());
    }

    public function test_paid_cancelled_and_cash_orders_do_not_start_a_payment(): void
    {
        Http::fake();

        $paid = Order::factory()->online()->paid()->create();
        $cancelled = Order::factory()->online()->cancelled()->create();
        $cash = Order::factory()->create();

        $this->post(route('payments.start', $paid->token))->assertRedirect(route('orders.show', $paid->token));
        $this->post(route('payments.start', $cancelled->token))->assertRedirect(route('orders.show', $cancelled->token));
        $this->post(route('payments.start', $cash->token))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_payment_cannot_start_when_online_payment_is_off(): void
    {
        $order = Order::factory()->online()->create();
        config(['restaurant.ordering.online_payment' => false]);
        Http::fake();

        $this->post(route('payments.start', $order->token))->assertNotFound();

        Http::assertNothingSent();
    }

    // Confirming a payment

    public function test_valid_payment_marks_the_order_paid_and_alerts_the_kitchen(): void
    {
        [$order, $payment] = $this->onlineOrder();
        $this->fakeValidation($payment);

        $this->callback($payment)->assertRedirect(route('orders.show', $order->token))->assertSessionHas('status');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('VAL123', $payment->val_id);
        $this->assertSame('BANK123', $payment->bank_tran_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Mail::assertQueued(NewOrderAlertMail::class, 1);

        // The gateway is asked to confirm the val_id, with our credentials.
        Http::assertSent(fn($r) => str_contains($r->url(), 'validationserverAPI.php')
            && str_contains($r->url(), 'val_id=VAL123')
            && str_contains($r->url(), 'store_id=teststore'));
    }

    public function test_replaying_the_same_callback_or_ipn_changes_nothing(): void
    {
        [$order, $payment] = $this->onlineOrder();
        $this->fakeValidation($payment);

        $this->callback($payment);
        $paidAt = $payment->fresh()->paid_at;

        $this->callback($payment);
        $this->post('/payments/sslcommerz/ipn', ['val_id' => 'VAL123'])->assertOk()->assertSee('OK');

        $this->assertEquals($paidAt, $payment->fresh()->paid_at);
        Mail::assertQueued(NewOrderAlertMail::class, 1);
        $this->assertSame(1, Payment::where('status', 'paid')->count());
    }

    public function test_ipn_alone_can_settle_a_payment(): void
    {
        [$order, $payment] = $this->onlineOrder();
        $this->fakeValidation($payment);

        $this->post('/payments/sslcommerz/ipn', ['val_id' => 'VAL123', 'tran_id' => $payment->tran_id])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_amount_or_currency_mismatch_is_not_accepted(): void
    {
        foreach ([['amount' => '1.00'], ['currency_type' => 'USD'], ['currency_type' => null]] as $override) {
            [$order, $payment] = $this->onlineOrder();
            $this->fakeValidation($payment, $override);

            $this->callback($payment)->assertRedirect(route('orders.show', $order->token))->assertSessionHas('error');

            $this->assertSame('initiated', $payment->fresh()->status);
            $this->assertSame('unpaid', $order->fresh()->payment_status);
        }

        Mail::assertNotQueued(NewOrderAlertMail::class);
    }

    public function test_gateway_says_invalid_means_not_paid(): void
    {
        foreach (['INVALID_TRANSACTION', 'FAILED', 'CANCELLED', 'UNATTEMPTED', ''] as $status) {
            [$order, $payment] = $this->onlineOrder();
            $this->fakeValidation($payment, ['status' => $status]);

            $this->callback($payment)->assertSessionHas('error');

            $this->assertSame('unpaid', $order->fresh()->payment_status);
        }
    }

    public function test_forged_callbacks_cannot_mark_an_order_paid(): void
    {
        [$order, $payment] = $this->onlineOrder();
        Http::fake(['*validationserverAPI.php*' => Http::response(['status' => 'INVALID_TRANSACTION'])]);

        // An attacker posts "everything is fine" to the success URL.
        $this->post('/payments/sslcommerz/success', [
            'val_id' => 'FAKE',
            'tran_id' => $payment->tran_id,
            'status' => 'VALID',
            'amount' => '440.00',
            'currency_type' => 'BDT',
        ])->assertSessionHas('error');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('initiated', $payment->fresh()->status);
    }

    public function test_callbacks_without_a_val_id_never_reach_the_gateway(): void
    {
        [$order, $payment] = $this->onlineOrder();
        Http::fake();

        $this->post('/payments/sslcommerz/success', ['tran_id' => $payment->tran_id, 'status' => 'VALID'])->assertSessionHas('error');
        $this->post('/payments/sslcommerz/success', ['val_id' => ['x'], 'tran_id' => $payment->tran_id])->assertSessionHas('error');
        $this->post('/payments/sslcommerz/ipn', [])->assertOk();

        Http::assertNothingSent();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_validation_for_an_unknown_transaction_is_ignored(): void
    {
        [, $payment] = $this->onlineOrder();
        Http::fake(['*validationserverAPI.php*' => Http::response([
            'status' => 'VALID',
            'tran_id' => 'MO-UNKNOWN0-ZZZZ',
            'amount' => '440.00',
            'currency_type' => 'BDT',
        ])]);

        $this->callback($payment)->assertSessionHas('error');

        $this->assertSame(0, Payment::where('status', 'paid')->count());
        $this->post('/payments/sslcommerz/success', ['val_id' => 'X', 'tran_id' => 'nope'])->assertRedirect(route('home'));
    }

    public function test_gateway_outages_do_not_mark_anything_paid(): void
    {
        [$order, $payment] = $this->onlineOrder();

        Http::fake(['*' => Http::response('', 500)]);
        $this->callback($payment)->assertSessionHas('error');

        Http::fake(['*' => fn() => throw new ConnectionException('boom')]);
        $this->callback($payment)->assertSessionHas('error');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_payment_for_a_cancelled_order_is_recorded_but_does_not_alert_the_kitchen(): void
    {
        [$order, $payment] = $this->onlineOrder(['status' => 'cancelled']);
        $this->fakeValidation($payment);

        $this->callback($payment);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        Mail::assertNotQueued(NewOrderAlertMail::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.after_cancel']);
    }

    public function test_fail_and_cancel_callbacks_only_touch_open_attempts(): void
    {
        [$orderA, $open] = $this->onlineOrder();
        [$orderB, $paid] = $this->onlineOrder();
        $paid->update(['status' => 'paid']);
        [$orderC, $other] = $this->onlineOrder();

        $this->post('/payments/sslcommerz/fail', ['tran_id' => $open->tran_id])
            ->assertRedirect(route('orders.show', $orderA->token))->assertSessionHas('error');
        $this->post('/payments/sslcommerz/fail', ['tran_id' => $paid->tran_id]);
        $this->post('/payments/sslcommerz/cancel', ['tran_id' => $other->tran_id])->assertSessionHas('error');

        $this->assertSame('failed', $open->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('cancelled', $other->fresh()->status);

        // The customer can try again from the order page.
        $this->get(route('orders.show', $orderA->token))->assertOk()->assertSee('Pay');
    }

    public function test_order_page_shows_the_pay_button_only_while_unpaid(): void
    {
        $unpaid = Order::factory()->online()->create();
        $paid = Order::factory()->online()->paid()->create();

        $this->get(route('orders.show', $unpaid->token))->assertSee(route('payments.start', $unpaid->token), false);
        $this->get(route('orders.show', $paid->token))->assertDontSee(route('payments.start', $paid->token), false);
    }
}
