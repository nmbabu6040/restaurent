<?php

namespace Tests\Feature;

use App\Mail\OrderUpdateMail;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KitchenReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-15 19:00:00'));
    }

    private function staff(): User
    {
        return User::factory()->staff()->create();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function posOrder(array $attributes = [], array $lines = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'preparing',
            'table_name' => 'T1',
            'address' => null,
            'phone' => '',
            'subtotal' => '0.00',
            'delivery_fee' => '0.00',
            'total' => '0.00',
        ], $attributes));

        foreach ($lines as [$name, $qty, $sent, $ready]) {
            $order->items()->create([
                'name_en' => $name,
                'quantity' => $qty,
                'sent_qty' => $sent,
                'ready_qty' => $ready,
                'unit_price' => '100.00',
                'line_total' => $qty * 100,
            ]);
        }

        return $order;
    }

    private function onlineOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create($attributes);
        $order->items()->create(['name_en' => 'Online Biryani', 'quantity' => 2, 'unit_price' => '380.00', 'line_total' => '760.00']);

        return $order;
    }

    // Kitchen screen

    public function test_the_kitchen_screen_is_for_counter_staff(): void
    {
        $this->get('/admin/kitchen')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->rider()->create())->get('/admin/kitchen')->assertForbidden();
        $this->actingAs($this->staff())->get('/admin/kitchen')->assertOk()->assertSee('Nothing waiting');
    }

    public function test_only_what_was_sent_to_the_kitchen_shows_up(): void
    {
        $this->posOrder([], [['Sent Dish', 2, 2, 0], ['Not Sent Dish', 1, 0, 0], ['Finished Dish', 1, 1, 1]]);

        $this->actingAs($this->staff())->get('/admin/kitchen')
            ->assertSee('2 &times; Sent Dish', false)
            ->assertDontSee('Not Sent Dish')
            ->assertDontSee('Finished Dish');
    }

    public function test_online_orders_show_after_the_office_accepts_them(): void
    {
        $this->onlineOrder(['status' => 'pending', 'name' => 'Pending Pete']);
        $this->onlineOrder(['status' => 'confirmed', 'name' => 'Confirmed Carl']);
        $this->onlineOrder(['status' => 'preparing', 'name' => 'Preparing Pam']);
        $this->onlineOrder(['status' => 'cancelled', 'name' => 'Cancelled Cleo']);
        $this->onlineOrder(['status' => 'completed', 'name' => 'Completed Colin']);

        $this->actingAs($this->staff())->get('/admin/kitchen')
            ->assertSee('Confirmed Carl')->assertSee('Preparing Pam')
            ->assertDontSee('Pending Pete')->assertDontSee('Cancelled Cleo')->assertDontSee('Completed Colin');
    }

    public function test_a_line_and_then_the_whole_ticket_can_be_marked_ready(): void
    {
        $order = $this->posOrder([], [['Dish A', 1, 1, 0], ['Dish B', 1, 1, 0]]);
        [$a, $b] = [$order->items[0], $order->items[1]];
        $staff = $this->staff();

        $this->actingAs($staff)->postJson(route('admin.kitchen.lines.ready', [$order, $a]))
            ->assertOk()->assertJsonPath('ids.0', $order->id)->assertSee('Dish B')->assertDontSee('Dish A');
        $this->assertSame(1, $a->fresh()->ready_qty);
        $this->assertNotNull($a->fresh()->ready_at);

        $this->actingAs($staff)->postJson(route('admin.kitchen.ready', $order))->assertOk()->assertJsonPath('ids', []);
        $this->assertSame(1, $b->fresh()->ready_qty);

        $this->actingAs($staff)->postJson(route('admin.kitchen.ready', $order))->assertStatus(422);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kitchen.ready', 'user_id' => $staff->id]);
    }

    public function test_extra_portions_added_after_a_dish_was_ready_come_back(): void
    {
        $this->posOrder([], [['Biryani', 3, 3, 2]]); // 3 sent, 2 ready: one more to cook

        $this->actingAs($this->staff())->getJson(route('admin.kitchen.tickets'))->assertOk()->assertSee('1 &times; Biryani', false);
    }

    public function test_a_line_from_another_order_cannot_be_marked(): void
    {
        $one = $this->posOrder([], [['Dish A', 1, 1, 0]]);
        $two = $this->posOrder(['table_name' => 'T2'], [['Dish B', 1, 1, 0]]);

        $this->actingAs($this->staff())->postJson(route('admin.kitchen.lines.ready', [$one, $two->items[0]]))->assertNotFound();
        $this->assertSame(0, $two->items[0]->fresh()->ready_qty);
    }

    public function test_a_ready_takeaway_order_tells_the_customer_but_a_delivery_order_does_not_change(): void
    {
        $pickup = $this->onlineOrder(['type' => 'pickup', 'address' => null, 'status' => 'preparing']);
        $delivery = $this->onlineOrder(['type' => 'delivery', 'status' => 'preparing']);
        $staff = $this->staff();

        $this->actingAs($staff)->postJson(route('admin.kitchen.ready', $pickup))->assertOk();
        $this->actingAs($staff)->postJson(route('admin.kitchen.ready', $delivery))->assertOk();

        $this->assertSame('ready', $pickup->fresh()->status->value);
        $this->assertSame('preparing', $delivery->fresh()->status->value);
        Mail::assertQueued(OrderUpdateMail::class, 1);
    }

    public function test_tickets_get_older_colours(): void
    {
        $this->posOrder(['table_name' => 'Fresh'], [['Dish', 1, 1, 0]])->items()->update(['sent_at' => now()->subMinutes(2)]);
        $this->posOrder(['table_name' => 'Slow'], [['Dish', 1, 1, 0]])->items()->update(['sent_at' => now()->subMinutes(12)]);
        $this->posOrder(['table_name' => 'Late'], [['Dish', 1, 1, 0]])->items()->update(['sent_at' => now()->subMinutes(25)]);

        $html = $this->actingAs($this->staff())->get('/admin/kitchen')->getContent();

        $this->assertStringContainsString('border-emerald-500', $html);
        $this->assertStringContainsString('border-amber-500', $html);
        $this->assertStringContainsString('border-red-500', $html);
        $this->assertLessThan(strpos($html, 'Slow'), strpos($html, 'Late')); // the oldest comes first
    }

    public function test_the_waiter_sees_when_food_is_ready(): void
    {
        $order = $this->posOrder(['payment_status' => 'unpaid'], [['Biryani', 1, 1, 1]]);
        $table = DiningTable::create(['name' => 'T1', 'seats' => 4, 'is_active' => true]);
        $order->update(['dining_table_id' => $table->id]);

        $this->actingAs($this->staff())
            ->get('/admin/pos')
            ->assertSee('Food', false)
            ->assertSee('ready', false);
        $this->actingAs($this->staff())->get(route('admin.pos.show', $order))->assertSee('ready to serve');

        $before = $this->actingAs($this->staff())->getJson(route('admin.pos.state'))->json('signature');
        $order->items()->update(['ready_qty' => 0]);
        $this->assertNotSame($before, $this->actingAs($this->staff())->getJson(route('admin.pos.state'))->json('signature'));
    }

    public function test_a_bill_paid_before_it_was_sent_still_reaches_the_kitchen(): void
    {
        $table = DiningTable::create(['name' => 'T1', 'seats' => 4, 'is_active' => true]);
        $dish = MenuItem::factory()->create(['name_en' => 'Fast Burger', 'price' => '200.00']);
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id])->assertRedirect();
        $order = Order::latest('id')->first();
        $this->actingAs($staff)->postJson(route('admin.pos.lines.store', $order), ['item_id' => $dish->id, 'quantity' => 2])->assertOk();
        $this->actingAs($staff)->post(route('admin.pos.pay.store', $order), ['payments' => [['method' => 'cash', 'amount' => '400.00']]])->assertRedirect();

        $this->assertNotNull($order->items()->first()->sent_at);
        $this->actingAs($staff)->get('/admin/kitchen')->assertSee('Fast Burger');
    }

    // Staff sales

    private function paidBill(User $by, string $total, array $extra = []): Order
    {
        $order = $this->posOrder(array_merge([
            'user_id' => $by->id,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'subtotal' => $total,
            'total' => $total,
            'guests' => 2,
        ], $extra));
        $order->items()->create(['name_en' => 'Dish', 'quantity' => 2, 'unit_price' => '100.00', 'line_total' => $total, 'sent_qty' => 2, 'ready_qty' => 2]);

        return $order;
    }

    public function test_staff_sales_are_added_up_per_person(): void
    {
        $rahim = User::factory()->staff()->create(['name' => 'Rahim']);
        $karim = User::factory()->staff()->create(['name' => 'Karim']);
        $this->paidBill($rahim, '500.00', ['discount' => '50.00']);
        $this->paidBill($rahim, '300.00');
        $this->paidBill($karim, '1000.00');
        $this->posOrder(['user_id' => $karim->id, 'status' => 'cancelled']);                    // cancelled: counted separately
        Order::factory()->create(['user_id' => $rahim->id, 'total' => '9999.00']);               // an online order never counts

        $rows = app(AnalyticsService::class)->staffSales(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(['Karim', 'Rahim'], array_column($rows, 'name'));       // best seller first
        $this->assertSame(1000.0, $rows[0]['revenue']);
        $this->assertSame(1, $rows[0]['cancelled']);
        $this->assertSame(800.0, $rows[1]['revenue']);
        $this->assertSame(2, $rows[1]['bills']);
        $this->assertSame(400.0, $rows[1]['average']);
        $this->assertSame(50.0, $rows[1]['discount']);
        $this->assertSame(4, $rows[1]['items']);
        $this->assertSame(4, $rows[1]['guests']);
    }

    public function test_the_staff_sales_page_and_download(): void
    {
        $waiter = User::factory()->staff()->create(['name' => '=cmd|waiter']);
        $this->paidBill($waiter, '500.00');
        $manager = $this->manager();

        $this->actingAs($manager)->get('/admin/staff-sales')->assertOk()->assertSee('Sales by staff member')->assertSee('data-chart', false);

        $csv = $this->actingAs($manager)->get('/admin/staff-sales/export?range=today')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFStaff,Bills,Sales", $csv);
        $this->assertStringContainsString("'=cmd|waiter", $csv);
        auth()->logout();
        $this->get('/admin/staff-sales')->assertRedirect('/admin/login');
        $this->actingAs($this->staff())->get('/admin/staff-sales')->assertForbidden();
        $this->actingAs($this->staff())->get('/admin/staff-sales/export')->assertForbidden();
    }

    public function test_restaurant_payments_can_be_downloaded(): void
    {
        $order = $this->paidBill($this->staff(), '500.00');
        $order->posPayments()->create(['method' => 'bkash', 'amount' => '500.00', 'reference' => 'TRX123']);

        $csv = $this->actingAs($this->manager())->get('/admin/downloads/pos_payments?format=csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('bKash', $csv);
        $this->assertStringContainsString('TRX123', $csv);
        $this->assertStringContainsString($order->reference, $csv);
    }

    // Reservations and tables

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'status' => 'confirmed',
            'reserved_at' => now()->addHour(),
            'party_size' => 4,
            'name' => 'Rahim Uddin',
            'phone' => '01712345678',
            'message' => 'Window seat please',
        ], $attributes));
    }

    public function test_a_table_can_be_assigned_to_a_reservation(): void
    {
        $table = DiningTable::create(['name' => 'T5', 'seats' => 4, 'is_active' => true]);
        $inactive = DiningTable::create(['name' => 'T6', 'seats' => 4, 'is_active' => false]);
        $reservation = $this->reservation();
        $staff = $this->staff();

        $this->actingAs($staff)->patch(route('admin.reservations.table', $reservation), ['dining_table_id' => $table->id])->assertSessionHas('status');
        $this->assertSame($table->id, $reservation->fresh()->dining_table_id);

        $this->actingAs($staff)->patch(route('admin.reservations.table', $reservation), ['dining_table_id' => $inactive->id])->assertSessionHasErrors('dining_table_id');
        $this->actingAs($staff)->patch(route('admin.reservations.table', $reservation), ['dining_table_id' => ''])->assertSessionHas('status');
        $this->assertNull($reservation->fresh()->dining_table_id);

        $this->actingAs($staff)->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('Reserved table');
    }

    public function test_a_reserved_table_is_marked_on_the_counter(): void
    {
        $table = DiningTable::create(['name' => 'T5', 'seats' => 4, 'is_active' => true]);
        $this->reservation(['dining_table_id' => $table->id]);
        $this->reservation(['dining_table_id' => $table->id, 'reserved_at' => now()->addDays(2), 'name' => 'Far Away']);

        $this->actingAs($this->staff())->get('/admin/pos')->assertSee('Reserved 8:00 PM')->assertSee('Rahim Uddin')->assertDontSee('Far Away');
    }

    public function test_seating_guests_opens_a_bill_with_their_details(): void
    {
        $table = DiningTable::create(['name' => 'T5', 'seats' => 4, 'is_active' => true]);
        $reservation = $this->reservation(['dining_table_id' => $table->id]);
        $staff = $this->staff();

        $response = $this->actingAs($staff)->post(route('admin.reservations.seat', $reservation));

        $order = Order::where('source', 'pos')->firstOrFail();
        $response->assertRedirect(route('admin.pos.show', $order));
        $this->assertSame('T5', $order->table_name);
        $this->assertSame('Rahim Uddin', $order->name);
        $this->assertSame('01712345678', $order->phone);
        $this->assertSame(4, $order->guests);
        $this->assertSame('Window seat please', $order->notes);
        $this->assertSame($order->id, $reservation->fresh()->seated_order_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'reservation.seated']);

        // Pressing it again goes to the same bill instead of opening a second one.
        $this->actingAs($staff)->post(route('admin.reservations.seat', $reservation))->assertRedirect(route('admin.pos.show', $order));
        $this->assertSame(1, Order::where('source', 'pos')->count());
    }

    public function test_seating_rules(): void
    {
        $table = DiningTable::create(['name' => 'T5', 'seats' => 4, 'is_active' => true]);
        $staff = $this->staff();

        $pending = $this->reservation(['status' => 'pending', 'dining_table_id' => $table->id]);
        $this->actingAs($staff)->post(route('admin.reservations.seat', $pending))->assertSessionHas('error');

        $noTable = $this->reservation();
        $this->actingAs($staff)->post(route('admin.reservations.seat', $noTable))->assertSessionHas('error');

        $this->posOrder(['table_name' => 'T5', 'dining_table_id' => $table->id, 'payment_status' => 'unpaid']);
        $busy = $this->reservation(['dining_table_id' => $table->id]);
        $this->actingAs($staff)->post(route('admin.reservations.seat', $busy))->assertSessionHas('error');
        $this->assertNull($busy->fresh()->seated_order_id);

        $this->actingAs(User::factory()->rider()->create())->post(route('admin.reservations.seat', $busy))->assertForbidden();
    }
}
