<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Mail\OrderUpdateMail;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
    }

    private function staff(): User
    {
        return User::factory()->staff()->create();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    /** @return array{0: User, 1: Rider} */
    private function rider(array $attributes = []): array
    {
        $user = User::factory()->rider()->create();

        return [$user, Rider::factory()->create(array_merge(['user_id' => $user->id], $attributes))];
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge(['type' => 'delivery', 'status' => 'preparing', 'total' => '440.00'], $attributes));
    }

    private function assign(Order $order, Rider $rider, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->staff())->post(route('admin.orders.rider', $order), ['rider_id' => $rider->id]);
    }

    private function delivery(Order $order, Rider $rider, array $attributes = []): Delivery
    {
        return Delivery::create(array_merge([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'assigned',
            'rider_fee' => '50.00',
            'assigned_at' => now(),
        ], $attributes));
    }

    // Riders and logins

    public function test_a_manager_creates_a_rider_with_a_login(): void
    {
        $this->actingAs($this->manager())->post(route('admin.riders.store'), [
            'name' => 'Karim',
            'phone' => '01711111111',
            'vehicle' => 'bike',
            'fee_per_delivery' => '60',
            'is_active' => 1,
            'login_email' => 'karim@example.com',
            'login_password' => 'Str0ng-pass-1',
        ])->assertRedirect();

        $rider = Rider::firstWhere('name', 'Karim');
        $this->assertSame('60.00', $rider->fee_per_delivery);
        $this->assertSame('rider', $rider->user->role->value);
        $this->assertSame('karim@example.com', $rider->user->email);
        $this->assertTrue(Hash::check('Str0ng-pass-1', $rider->user->password));
    }

    public function test_rider_validation(): void
    {
        $manager = $this->manager();
        $valid = ['name' => 'Karim', 'phone' => '01711111111', 'vehicle' => 'bike', 'fee_per_delivery' => '60', 'is_active' => 1];

        $this->actingAs($manager)->post(route('admin.riders.store'), [])->assertSessionHasErrors(['name', 'phone', 'vehicle', 'fee_per_delivery']);
        $this->actingAs($manager)->post(route('admin.riders.store'), array_merge($valid, ['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->actingAs($manager)->post(route('admin.riders.store'), array_merge($valid, ['vehicle' => 'rocket']))->assertSessionHasErrors('vehicle');
        $this->actingAs($manager)->post(route('admin.riders.store'), array_merge($valid, ['fee_per_delivery' => '-1']))->assertSessionHasErrors('fee_per_delivery');
        $this->actingAs($manager)->post(route('admin.riders.store'), array_merge($valid, ['login_email' => 'k@example.com']))->assertSessionHasErrors('login_password');
        $this->actingAs($manager)->post(route('admin.riders.store'), array_merge($valid, ['login_email' => 'k@example.com', 'login_password' => 'abc']))->assertSessionHasErrors('login_password');
        $this->actingAs($manager)->post(route('admin.riders.store'), array_merge($valid, ['login_email' => $manager->email, 'login_password' => 'Str0ng-pass-1']))->assertSessionHasErrors('login_email');

        $this->assertDatabaseCount('riders', 0);
    }

    public function test_riders_cannot_reach_the_admin(): void
    {
        [$user] = $this->rider();

        foreach (['/admin', '/admin/orders', '/admin/riders', '/admin/sales'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_a_rider_logs_in_to_their_own_panel(): void
    {
        [$user] = $this->rider();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('rider.dashboard'));
    }

    public function test_only_active_riders_with_a_profile_can_open_the_panel(): void
    {
        $this->get('/rider')->assertRedirect('/admin/login');
        $this->actingAs($this->staff())->get('/rider')->assertForbidden();
        $this->actingAs($this->manager())->get('/rider')->assertForbidden();
        $this->actingAs(User::factory()->rider()->create())->get('/rider')->assertForbidden(); // no rider profile

        [, $inactive] = $this->rider(['is_active' => false]);
        $this->actingAs($inactive->user)->get('/rider')->assertForbidden();

        [$user] = $this->rider();
        $this->actingAs($user)->get('/rider')->assertOk()->assertSee('My deliveries');
    }

    public function test_riders_do_not_appear_in_the_users_list_and_cannot_be_made_there(): void
    {
        [$riderUser] = $this->rider();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertDontSee($riderUser->email);
        $this->actingAs($admin)->get(route('admin.users.edit', $riderUser))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'role' => 'rider',
            'is_active' => 1,
            'password' => 'Str0ng-pass-1',
        ])->assertSessionHasErrors('role');
    }

    public function test_a_rider_with_open_deliveries_cannot_be_removed(): void
    {
        [, $rider] = $this->rider();
        $this->delivery($this->order(), $rider);

        $this->actingAs($this->manager())->delete(route('admin.riders.destroy', $rider))->assertSessionHas('error');
        $this->assertNotSoftDeleted($rider);

        Delivery::query()->update(['status' => 'delivered', 'delivered_at' => now(), 'settled_at' => now()]);
        $this->actingAs($this->manager())->delete(route('admin.riders.destroy', $rider))->assertRedirect();
        $this->assertSoftDeleted($rider);
        $this->assertFalse($rider->user->fresh()->is_active);
    }

    public function test_staff_cannot_manage_riders(): void
    {
        $this->actingAs($this->staff())->get('/admin/riders')->assertForbidden();
        $this->actingAs($this->staff())->post(route('admin.riders.store'), ['name' => 'X'])->assertForbidden();
    }

    // Assigning

    public function test_staff_can_assign_a_rider_and_the_fee_is_saved(): void
    {
        [, $rider] = $this->rider(['fee_per_delivery' => '70.00']);
        $order = $this->order(['status' => 'confirmed']);

        $this->assign($order, $rider)->assertSessionHas('status');

        $delivery = $order->delivery;
        $this->assertSame($rider->id, $delivery->rider_id);
        $this->assertSame(DeliveryStatus::Assigned, $delivery->status);
        $this->assertSame('70.00', $delivery->rider_fee);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delivery.assigned']);

        $rider->update(['fee_per_delivery' => '99.00']);
        $this->assertSame('70.00', $delivery->fresh()->rider_fee); // later fee changes do not touch it
    }

    public function test_assignment_rules(): void
    {
        [, $rider] = $this->rider();
        [, $inactive] = $this->rider(['is_active' => false]);

        $this->assign($this->order(['status' => 'pending']), $rider)->assertSessionHas('error');                 // not confirmed yet
        $this->assign($this->order(['type' => 'pickup', 'address' => null]), $rider)->assertSessionHas('error'); // pickup order
        $this->assign($this->order(), $inactive)->assertSessionHas('error');                                      // inactive rider
        $this->assign($this->order(['status' => 'completed']), $rider)->assertSessionHas('error');               // finished
        $this->actingAs($this->staff())->post(route('admin.orders.rider', $this->order()), ['rider_id' => 9999])->assertSessionHasErrors('rider_id');

        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_reassigning_resets_the_delivery_but_a_delivered_one_is_locked(): void
    {
        [, $first] = $this->rider(['fee_per_delivery' => '50.00']);
        [, $second] = $this->rider(['fee_per_delivery' => '80.00']);
        $order = $this->order();
        $this->delivery($order, $first, ['status' => 'failed', 'failed_reason' => 'No answer']);

        $this->assign($order, $second)->assertSessionHas('status');

        $delivery = $order->delivery->fresh();
        $this->assertSame($second->id, $delivery->rider_id);
        $this->assertSame(DeliveryStatus::Assigned, $delivery->status);
        $this->assertSame('80.00', $delivery->rider_fee);
        $this->assertNull($delivery->failed_reason);
        $this->assertSame(1, Delivery::count());

        $delivery->update(['status' => 'delivered']);
        $this->assign($order, $first)->assertSessionHas('error');
    }

    public function test_orders_needing_a_rider(): void
    {
        [, $rider] = $this->rider();
        $needs = $this->order(['status' => 'confirmed']);
        $this->delivery($this->order(), $rider);                                                // has a rider
        $this->order(['status' => 'pending']);                                                 // not confirmed
        $this->order(['type' => 'pickup', 'address' => null, 'status' => 'confirmed']);        // pickup
        $failed = $this->order();
        $this->delivery($failed, $rider, ['status' => 'failed', 'failed_reason' => 'x']);      // rider gave up

        $this->assertEqualsCanonicalizing([$needs->id, $failed->id], Order::needsRider()->pluck('id')->all());
        $this->actingAs($this->staff())->get('/admin/orders?needs_rider=1')->assertOk()->assertSee($needs->reference);
    }

    // The rider's work

    public function test_picking_up_moves_a_preparing_order_out_for_delivery(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order();
        $delivery = $this->delivery($order, $rider);

        $this->actingAs($user)->post(route('rider.deliveries.pickup', $delivery))->assertSessionHas('status');

        $this->assertSame(DeliveryStatus::PickedUp, $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->picked_up_at);
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
        Mail::assertQueued(OrderUpdateMail::class);
    }

    public function test_pickup_waits_for_the_kitchen(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order(['status' => 'confirmed']);
        $delivery = $this->delivery($order, $rider);

        $this->actingAs($user)->post(route('rider.deliveries.pickup', $delivery))->assertSessionHas('error');

        $this->assertSame(DeliveryStatus::Assigned, $delivery->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status->value);
    }

    public function test_delivering_a_cash_order_records_the_cash_and_marks_it_paid(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order(['status' => 'out_for_delivery']);
        $delivery = $this->delivery($order, $rider, ['status' => 'picked_up', 'picked_up_at' => now()]);

        $this->actingAs($user)->post(route('rider.deliveries.deliver', $delivery))->assertRedirect(route('rider.dashboard'));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertSame('440.00', $delivery->cash_collected);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame('completed', $order->fresh()->status->value);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_delivering_an_online_paid_order_collects_no_cash(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order(['status' => 'out_for_delivery', 'payment_method' => 'sslcommerz', 'payment_status' => 'paid']);
        $delivery = $this->delivery($order, $rider, ['status' => 'picked_up']);

        $this->actingAs($user)->post(route('rider.deliveries.deliver', $delivery));

        $this->assertSame('0.00', $delivery->fresh()->cash_collected);
        $this->assertSame('completed', $order->fresh()->status->value);
    }

    public function test_delivery_needs_a_pickup_first_and_cannot_be_repeated(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order(['status' => 'out_for_delivery']);
        $delivery = $this->delivery($order, $rider); // still "assigned"

        $this->actingAs($user)->post(route('rider.deliveries.deliver', $delivery))->assertSessionHas('error');
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);

        $delivery->update(['status' => 'picked_up']);
        $this->actingAs($user)->post(route('rider.deliveries.deliver', $delivery))->assertRedirect();
        $this->actingAs($user)->post(route('rider.deliveries.deliver', $delivery))->assertSessionHas('error');
        $this->assertSame(1, Delivery::where('status', 'delivered')->count());
    }

    public function test_a_rider_only_sees_and_changes_their_own_deliveries(): void
    {
        [$user] = $this->rider();
        [, $other] = $this->rider();
        $theirs = $this->delivery($this->order(['name' => 'Theirs Customer']), $other);

        $this->actingAs($user)->get(route('rider.deliveries.show', $theirs))->assertNotFound();
        $this->actingAs($user)->post(route('rider.deliveries.pickup', $theirs))->assertNotFound();
        $this->actingAs($user)->post(route('rider.deliveries.deliver', $theirs))->assertNotFound();
        $this->actingAs($user)->post(route('rider.deliveries.problem', $theirs), ['reason' => 'nope'])->assertNotFound();
        $this->actingAs($user)->get('/rider')->assertOk()->assertDontSee('Theirs Customer');

        $this->assertSame(DeliveryStatus::Assigned, $theirs->fresh()->status);
    }

    public function test_the_delivery_page_shows_what_the_rider_needs(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order(['address' => 'House 7, Road 3', 'notes' => 'Ring the bell twice']);
        $order->items()->create(['name_en' => 'Kacchi Biryani', 'quantity' => 2, 'unit_price' => '380.00', 'line_total' => '760.00']);
        $delivery = $this->delivery($order, $rider);

        $this->actingAs($user)->get(route('rider.deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('House 7, Road 3')
            ->assertSee('Ring the bell twice')
            ->assertSee('Collect in cash')
            ->assertSee('Kacchi Biryani')
            ->assertSee('google.com/maps/search', false)
            ->assertSee('I have picked up the order');
    }

    public function test_reporting_a_problem_needs_a_reason_and_leaves_the_order_alone(): void
    {
        [$user, $rider] = $this->rider();
        $order = $this->order(['status' => 'out_for_delivery']);
        $delivery = $this->delivery($order, $rider, ['status' => 'picked_up']);

        $this->actingAs($user)->post(route('rider.deliveries.problem', $delivery), [])->assertSessionHasErrors('reason');
        $this->assertSame(DeliveryStatus::PickedUp, $delivery->fresh()->status);

        $this->actingAs($user)->post(route('rider.deliveries.problem', $delivery), ['reason' => 'Customer not answering'])->assertRedirect();

        $this->assertSame(DeliveryStatus::Failed, $delivery->fresh()->status);
        $this->assertSame('Customer not answering', $delivery->fresh()->failed_reason);
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
        $this->assertSame(1, Order::needsRider()->count());

        $this->actingAs($this->staff())->get(route('admin.orders.show', $order))->assertSee('Customer not answering');
    }

    public function test_going_online_and_offline(): void
    {
        [$user, $rider] = $this->rider(['is_online' => false]);

        $this->actingAs($user)->patch(route('rider.online'))->assertRedirect();
        $this->assertTrue($rider->fresh()->is_online);

        $this->actingAs($user)->patch(route('rider.online'));
        $this->assertFalse($rider->fresh()->is_online);
    }

    public function test_the_poll_signature_changes_with_new_work(): void
    {
        [$user, $rider] = $this->rider();

        $empty = $this->actingAs($user)->getJson(route('rider.poll'))->assertOk()->json('signature');
        $delivery = $this->delivery($this->order(), $rider);
        $busy = $this->actingAs($user)->getJson(route('rider.poll'))->json('signature');

        $this->assertSame('', $empty);
        $this->assertSame($delivery->id . ':assigned', $busy);
    }

    // The office and the rider stay in step

    public function test_completing_the_order_in_the_office_closes_the_delivery_with_its_cash(): void
    {
        [, $rider] = $this->rider();
        $order = $this->order(['status' => 'out_for_delivery']);
        $delivery = $this->delivery($order, $rider, ['status' => 'picked_up']);

        $this->actingAs($this->staff())->patch(route('admin.orders.status', $order), ['status' => 'completed'])->assertSessionHas('status');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertSame('440.00', $delivery->cash_collected);
    }

    public function test_cancelling_an_order_cancels_its_open_delivery(): void
    {
        [, $rider] = $this->rider();
        $order = $this->order();
        $delivery = $this->delivery($order, $rider);

        $this->actingAs($this->staff())->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHas('status');

        $this->assertSame(DeliveryStatus::Cancelled, $delivery->fresh()->status);
    }

    // What the customer sees

    public function test_the_customer_sees_the_rider_and_gets_the_phone_after_pickup(): void
    {
        [, $rider] = $this->rider(['name' => 'Rider Karim', 'phone' => '01799999999']);
        $order = $this->order(['status' => 'preparing']);
        $delivery = $this->delivery($order, $rider);

        $this->get(route('orders.show', $order->token))->assertSee('Your delivery rider')->assertSee('Rider Karim')->assertDontSee('01799999999');

        $delivery->update(['status' => 'picked_up']);
        $order->update(['status' => 'out_for_delivery']);
        $this->get(route('orders.show', $order->token))->assertSee('01799999999');

        $delivery->update(['status' => 'delivered']);
        $order->update(['status' => 'completed']);
        $this->get(route('orders.show', $order->token))->assertDontSee('Rider Karim');
    }

    public function test_the_tracking_signature_changes_when_a_rider_is_assigned(): void
    {
        [, $rider] = $this->rider();
        $order = $this->order();

        $before = $this->getJson(route('orders.status', $order->token))->json('signature');
        $this->delivery($order, $rider);
        $after = $this->getJson(route('orders.status', $order->token))->json('signature');

        $this->assertSame('preparing|', $before);
        $this->assertSame('preparing|assigned', $after);
    }

    // Settlement and profit

    private function deliveredLine(Rider $rider, string $cash, string $fee, array $extra = []): Delivery
    {
        return $this->delivery($this->order(), $rider, array_merge([
            'status' => 'delivered',
            'rider_fee' => $fee,
            'cash_collected' => $cash,
            'delivered_at' => now(),
        ], $extra));
    }

    public function test_settlement_shows_the_numbers_and_closes_the_books(): void
    {
        [, $rider] = $this->rider();
        $this->deliveredLine($rider, '440.00', '50.00');
        $this->deliveredLine($rider, '0.00', '50.00');
        $this->deliveredLine($rider, '300.00', '50.00', ['settled_at' => now()->subDay()]); // already settled
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('admin.riders.show', $rider))
            ->assertOk()->assertViewHas('cash', 440.0)->assertViewHas('fees', 100.0)->assertSee('Settle all');

        $this->actingAs($manager)->post(route('admin.riders.settle', $rider))->assertSessionHas('status');

        $this->assertSame(0, Delivery::where('rider_id', $rider->id)->whereNull('settled_at')->count());
        $this->assertSame($manager->id, Delivery::whereNotNull('settled_by')->first()->settled_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'rider.settled']);

        $this->actingAs($manager)->post(route('admin.riders.settle', $rider))->assertSessionHas('error'); // nothing left
    }

    public function test_only_delivered_deliveries_are_settled_and_staff_cannot_settle(): void
    {
        [, $rider] = $this->rider();
        $open = $this->delivery($this->order(), $rider);
        $this->deliveredLine($rider, '440.00', '50.00');

        $this->actingAs($this->staff())->post(route('admin.riders.settle', $rider))->assertForbidden();
        $this->actingAs($this->manager())->post(route('admin.riders.settle', $rider));

        $this->assertNull($open->fresh()->settled_at);
        $this->assertSame(1, Delivery::whereNotNull('settled_at')->count());
    }

    public function test_the_riders_list_shows_unsettled_totals(): void
    {
        [, $rider] = $this->rider(['name' => 'Rider Rahim']);
        $this->deliveredLine($rider, '440.00', '50.00');

        $this->actingAs($this->manager())->get('/admin/riders')->assertOk()->assertSee('Rider Rahim')->assertSee('440');
    }

    public function test_rider_fees_are_a_cost_in_profit_and_loss_on_the_day_of_delivery(): void
    {
        [, $rider] = $this->rider();
        $this->deliveredLine($rider, '0.00', '50.00');                                                        // today
        $this->deliveredLine($rider, '0.00', '70.00', ['delivered_at' => Carbon::parse('2026-11-10 12:00')]); // earlier
        $this->delivery($this->order(), $rider, ['status' => 'failed', 'rider_fee' => '999.00']);             // not delivered: no cost

        $analytics = app(AnalyticsService::class);
        $today = $analytics->profitLoss(Carbon::parse('2026-11-15')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily');

        $this->assertSame(50.0, $today['summary']['courier']);
        $this->assertSame(-50.0, $today['summary']['net']);
        $this->assertSame(50.0, $today['series'][0]['costs']);

        $week = $analytics->profitLoss(Carbon::parse('2026-11-09')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily', false);
        $this->assertSame(120.0, $week['summary']['courier']);

        $this->actingAs($this->manager())->get('/admin/profit-loss?range=today')->assertOk()->assertSee('Rider delivery fees');
    }

    public function test_the_dashboard_counts_orders_that_need_a_rider(): void
    {
        $this->order(['status' => 'confirmed']);

        $this->actingAs($this->staff())->get('/admin')
            ->assertOk()->assertViewHas('stats', fn($stats) => collect($stats)->firstWhere('label', 'Need a rider')['value'] === 1);
    }
}
