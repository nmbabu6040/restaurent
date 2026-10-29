<?php

namespace Tests\Feature;

use App\Mail\OrderUpdateMail;
use App\Mail\ReservationStatusMail;
use App\Models\Coupon;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->staff = User::factory()->staff()->create();
    }

    // Reservations

    public function test_reservation_can_be_confirmed_and_the_guest_is_emailed(): void
    {
        $reservation = Reservation::factory()->create();

        $this->actingAs($this->staff)->patch(route('admin.reservations.status', $reservation), ['status' => 'confirmed'])
            ->assertSessionHas('status');

        $this->assertSame('confirmed', $reservation->fresh()->status->value);
        Mail::assertQueued(ReservationStatusMail::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'reservation.confirmed', 'user_id' => $this->staff->id]);
    }

    public function test_reservation_status_rules(): void
    {
        $confirmed = Reservation::factory()->create(['status' => 'confirmed']);
        $cancelled = Reservation::factory()->create(['status' => 'cancelled']);

        // Confirmed can only be cancelled, never rejected.
        $this->actingAs($this->staff)->patch(route('admin.reservations.status', $confirmed), ['status' => 'rejected'])
            ->assertSessionHas('error');
        $this->assertSame('confirmed', $confirmed->fresh()->status->value);

        // A cancelled reservation cannot be revived (its seats may already be rebooked).
        $this->actingAs($this->staff)->patch(route('admin.reservations.status', $cancelled), ['status' => 'confirmed'])
            ->assertSessionHas('error');
        $this->assertSame('cancelled', $cancelled->fresh()->status->value);

        $this->actingAs($this->staff)->patch(route('admin.reservations.status', $confirmed), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->actingAs($this->staff)->patch(route('admin.reservations.status', $confirmed), ['status' => 'cancelled'])
            ->assertSessionHas('status');
        $this->assertSame('cancelled', $confirmed->fresh()->status->value);
    }

    public function test_reservation_list_filters(): void
    {
        Reservation::factory()->create(['name' => 'Rahim Uddin', 'status' => 'pending', 'reserved_at' => '2026-12-05 19:00:00']);
        Reservation::factory()->create(['name' => 'Karim Mia', 'status' => 'confirmed', 'reserved_at' => '2026-12-06 19:00:00']);

        $this->actingAs($this->staff)->get(route('admin.reservations.index', ['status' => 'confirmed']))
            ->assertSee('Karim Mia')->assertDontSee('Rahim Uddin');

        $this->actingAs($this->staff)->get(route('admin.reservations.index', ['date' => '2026-12-05']))
            ->assertSee('Rahim Uddin')->assertDontSee('Karim Mia');

        $this->actingAs($this->staff)->get(route('admin.reservations.index', ['q' => 'Karim']))
            ->assertSee('Karim Mia')->assertDontSee('Rahim Uddin');

        $this->actingAs($this->staff)->get(route('admin.reservations.show', Reservation::first()))->assertOk();
    }

    // Messages

    public function test_message_is_marked_read_and_html_is_escaped(): void
    {
        $message = ContactMessage::create([
            'name' => 'Visitor',
            'email' => 'v@example.com',
            'message' => '<script>alert(1)</script>',
            'locale' => 'en',
        ]);

        $this->actingAs($this->staff)->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->assertNotNull($message->fresh()->read_at);
    }

    // Orders

    private function updateOrder(Order $order, string $status)
    {
        return $this->actingAs($this->staff)->patch(route('admin.orders.status', $order), ['status' => $status]);
    }

    public function test_delivery_order_full_flow_marks_cash_orders_paid(): void
    {
        $order = Order::factory()->create();

        foreach (['confirmed', 'preparing', 'out_for_delivery', 'completed'] as $status) {
            $this->updateOrder($order, $status)->assertSessionHas('status');
            $this->assertSame($status, $order->fresh()->status->value);
        }

        $this->assertSame('paid', $order->fresh()->payment_status);
        Mail::assertQueued(OrderUpdateMail::class);
    }

    public function test_pickup_order_uses_ready_instead_of_out_for_delivery(): void
    {
        $order = Order::factory()->pickup()->create(['status' => 'preparing']);

        $this->updateOrder($order, 'out_for_delivery')->assertSessionHas('error');
        $this->updateOrder($order, 'ready')->assertSessionHas('status');
        $this->updateOrder($order, 'completed')->assertSessionHas('status');
    }

    public function test_orders_cannot_skip_steps_or_leave_a_final_state(): void
    {
        $order = Order::factory()->create();

        $this->updateOrder($order, 'completed')->assertSessionHas('error');
        $this->updateOrder($order, 'preparing')->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status->value);

        $this->updateOrder($order, 'cancelled')->assertSessionHas('status');
        $this->updateOrder($order, 'confirmed')->assertSessionHas('error');
        $this->assertSame('cancelled', $order->fresh()->status->value);

        $this->updateOrder($order, 'nonsense')->assertSessionHasErrors('status');
    }

    public function test_unpaid_online_orders_cannot_be_confirmed_until_paid(): void
    {
        $order = Order::factory()->online()->create();

        $this->updateOrder($order, 'confirmed')->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status->value);

        $order->update(['payment_status' => 'paid']);

        $this->updateOrder($order, 'confirmed')->assertSessionHas('status');
        $this->assertSame('confirmed', $order->fresh()->status->value);
    }

    public function test_cancelling_gives_the_coupon_back_and_warns_about_refunds(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'WELCOME10', 'used_count' => 1]);
        $order = Order::factory()->online()->paid()->create(['coupon_code' => 'WELCOME10', 'discount' => '38.00']);

        $this->updateOrder($order, 'cancelled')->assertSessionHas('status');

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertStringContainsString('refund', session('status'));
    }

    public function test_order_pages_render_with_items_and_payments(): void
    {
        $order = Order::factory()->online()->create();
        $order->items()->create([
            'name_en' => 'Kacchi Biryani',
            'variation_en' => 'Full',
            'addons' => [['name_en' => 'Egg', 'name_bn' => null, 'price' => 30]],
            'unit_price' => '410.00',
            'quantity' => 1,
            'line_total' => '410.00',
        ]);
        $order->payments()->create([
            'gateway' => 'sslcommerz',
            'tran_id' => $order->reference . '-AB12',
            'amount' => '440.00',
            'status' => 'initiated',
        ]);

        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee($order->reference)->assertSee('Full, + Egg')->assertSee('Payment attempts');

        $this->actingAs($this->staff)->get(route('admin.orders.index'))->assertOk()->assertSee($order->reference);
    }

    public function test_dashboard_only_counts_orders_the_kitchen_can_act_on(): void
    {
        Order::factory()->create();                  // cash order: counts
        Order::factory()->online()->paid()->create(); // paid online: counts
        Order::factory()->online()->create();         // waiting for payment: does not count

        $this->actingAs($this->staff)->get('/admin')
            ->assertOk()
            ->assertViewHas('stats', fn($stats) => collect($stats)->firstWhere('label', 'Pending orders')['value'] === 2);
    }

    public function test_unpaid_online_orders_expire(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'WELCOME10', 'used_count' => 1]);

        $stale = Order::factory()->online()->create(['created_at' => now()->subHours(2), 'coupon_code' => 'WELCOME10']);
        $fresh = Order::factory()->online()->create();
        $paid = Order::factory()->online()->paid()->create(['created_at' => now()->subHours(2)]);
        $cash = Order::factory()->create(['created_at' => now()->subHours(2)]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $stale->fresh()->status->value);
        $this->assertSame('pending', $fresh->fresh()->status->value);
        $this->assertSame('pending', $paid->fresh()->status->value);
        $this->assertSame('pending', $cash->fresh()->status->value);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }
}
