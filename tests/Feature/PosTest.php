<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\DiningTable;
use App\Models\FoodAddon;
use App\Models\FoodVariation;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RecipeLine;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->staff()->create();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function table(string $name = 'T1'): DiningTable
    {
        return DiningTable::create(['name' => $name, 'seats' => 4, 'sort_order' => 1, 'is_active' => true]);
    }

    private function dish(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge(['name_en' => 'Kacchi Biryani', 'price' => '380.00'], $attributes));
    }

    private function openBill(?DiningTable $table = null, ?User $by = null): Order
    {
        $table ??= $this->table();
        $this->actingAs($by ?? $this->staff())
            ->post(route('admin.pos.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id, 'guests' => 3])
            ->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function add(Order $order, MenuItem $item, int $qty = 1, array $extra = [], ?User $by = null)
    {
        return $this->actingAs($by ?? $this->staff())
            ->postJson(route('admin.pos.lines.store', $order), array_merge(['item_id' => $item->id, 'quantity' => $qty], $extra));
    }

    private function pay(Order $order, array $payments, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->staff())->post(route('admin.pos.pay.store', $order), ['payments' => $payments]);
    }

    // Access and tables

    public function test_the_counter_is_for_staff_managers_and_admins(): void
    {
        $this->get('/admin/pos')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->rider()->create())->get('/admin/pos')->assertForbidden();

        foreach ([$this->staff(), $this->manager(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/admin/pos')->assertOk();
        }
    }

    public function test_managers_manage_the_tables_and_the_counter_shows_them(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.content.store', 'tables'), ['name' => 'Window 1', 'section' => 'Ground floor', 'seats' => 4, 'is_active' => 1])->assertRedirect();
        $this->assertSame(1, DiningTable::count());

        $this->actingAs($manager)->post(route('admin.content.store', 'tables'), ['name' => 'Window 1', 'seats' => 4, 'is_active' => 1])->assertSessionHasErrors('name');
        $this->actingAs($this->staff())->get('/admin/content/tables')->assertForbidden();

        $this->actingAs($this->staff())->get('/admin/pos')->assertSee('Window 1')->assertSee('Free');
    }

    // Opening a bill

    public function test_a_dine_in_bill_needs_a_free_table(): void
    {
        $table = $this->table();
        $order = $this->openBill($table);

        $this->assertSame('pos', $order->source);
        $this->assertSame('dine_in', $order->type->value);
        $this->assertSame('T1', $order->table_name);
        $this->assertSame(3, $order->guests);
        $this->assertSame('', $order->phone);
        $this->assertSame('pending', $order->status->value);
        $this->assertNotNull($order->user_id);

        $staff = $this->staff();
        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id])->assertSessionHas('error');
        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'dine_in'])->assertSessionHas('error');
        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'delivery'])->assertSessionHasErrors('type');
        $this->assertSame(1, Order::count());

        $table->update(['is_active' => false]);
        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id])->assertSessionHas('error');
    }

    public function test_takeaway_bills_need_no_table(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'pickup', 'name' => 'Rahim'])->assertRedirect();
        $this->actingAs($staff)->post(route('admin.pos.store'), ['type' => 'pickup'])->assertRedirect();

        $this->assertSame(2, Order::posOpen()->count());
        $this->assertSame('Rahim', Order::orderBy('id')->first()->name);
        $this->assertSame('Walk-in', Order::orderByDesc('id')->first()->name);
        $this->assertNull(Order::first()->table_name);
    }

    // Adding items

    public function test_lines_are_priced_on_the_server(): void
    {
        $item = $this->dish(['price' => '300.00']);
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Half', 'price' => '250.00']);
        $egg = FoodAddon::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Egg', 'price' => '30.00']);
        $order = $this->openBill();

        $this->add($order, $item, 2, ['variation_id' => $half->id, 'addons' => [$egg->id], 'price' => '1', 'unit_price' => 1])
            ->assertOk()->assertJsonStructure(['html']);

        $order->refresh();
        $this->assertSame('560.00', $order->subtotal);   // (250 + 30) x 2
        $this->assertSame('560.00', $order->total);

        $line = $order->items->first();
        $this->assertSame('280.00', $line->unit_price);
        $this->assertSame('Half', $line->variation_en);
        $this->assertSame(0, $line->sent_qty);
    }

    public function test_choices_are_checked(): void
    {
        $item = $this->dish();
        FoodVariation::factory()->create(['menu_item_id' => $item->id, 'price' => '250.00']);
        $foreign = FoodAddon::factory()->create();
        $order = $this->openBill();

        $this->add($order, $item)->assertStatus(422);                                               // size is required
        $this->add($order, $this->dish(['name_en' => 'Dal']), 1, ['addons' => [$foreign->id]])->assertStatus(422);
        $this->add($order, $item, 0)->assertStatus(422);
        $this->postJson(route('admin.pos.lines.store', $order), ['item_id' => 9999])->assertStatus(422);

        $this->assertSame(0, $order->items()->count());
    }

    public function test_unavailable_dishes_cannot_be_added(): void
    {
        $order = $this->openBill();
        $hidden = MenuItem::factory()->create(['menu_category_id' => MenuCategory::factory()->inactive()->create()->id]);

        $this->add($order, $this->dish(['is_available' => false]))->assertStatus(422);
        $this->add($order, $hidden)->assertStatus(422);
    }

    public function test_the_same_dish_is_merged_into_one_line(): void
    {
        $item = $this->dish();
        $order = $this->openBill();

        $this->add($order, $item);
        $this->add($order, $item, 2);

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(3, $order->items()->first()->quantity);
        $this->assertSame('1140.00', $order->fresh()->total);
    }

    public function test_quantities_can_be_changed_and_lines_removed(): void
    {
        $item = $this->dish();
        $order = $this->openBill();
        $this->add($order, $item);
        $line = $order->items()->first();
        $staff = $this->staff();

        $this->actingAs($staff)->patchJson(route('admin.pos.lines.update', [$order, $line]), ['quantity' => 4])->assertOk();
        $this->assertSame('1520.00', $order->fresh()->total);

        $this->actingAs($staff)->patchJson(route('admin.pos.lines.update', [$order, $line]), ['quantity' => 0])->assertOk();
        $this->assertSame(0, $order->items()->count());
        $this->assertSame('0.00', $order->fresh()->total);

        $this->add($order, $item);
        $this->actingAs($staff)->deleteJson(route('admin.pos.lines.destroy', [$order, $order->items()->first()]))->assertOk();
        $this->assertSame(0, $order->items()->count());
    }

    // Discount and service charge

    public function test_a_staff_member_has_a_discount_limit_and_a_manager_does_not(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish(['price' => '500.00']), 2); // 1000
        $staff = $this->staff();

        $this->actingAs($staff)->postJson(route('admin.pos.discount', $order), ['type' => 'percent', 'value' => 5, 'reason' => 'Regular guest'])->assertOk();
        $this->assertSame('950.00', $order->fresh()->total);
        $this->assertSame('Regular guest', $order->fresh()->discount_reason);

        $this->actingAs($staff)->postJson(route('admin.pos.discount', $order), ['type' => 'percent', 'value' => 20])->assertStatus(422);
        $this->actingAs($staff)->postJson(route('admin.pos.discount', $order), ['type' => 'fixed', 'value' => 300])->assertStatus(422);
        $this->assertSame('950.00', $order->fresh()->total);

        $this->actingAs($this->manager())->postJson(route('admin.pos.discount', $order), ['type' => 'percent', 'value' => 20])->assertOk();
        $this->assertSame('800.00', $order->fresh()->total);

        $this->actingAs($this->manager())->postJson(route('admin.pos.discount', $order), ['type' => 'fixed', 'value' => 2000])->assertStatus(422); // more than the bill
        $this->actingAs($this->manager())->postJson(route('admin.pos.discount', $order), ['type' => 'percent', 'value' => 0])->assertOk();
        $this->assertSame('1000.00', $order->fresh()->total);
    }

    public function test_a_percent_discount_follows_the_bill_as_it_grows(): void
    {
        $order = $this->openBill();
        $item = $this->dish(['price' => '500.00']);
        $this->add($order, $item, 2);
        $this->actingAs($this->manager())->postJson(route('admin.pos.discount', $order), ['type' => 'percent', 'value' => 10]);
        $this->assertSame('900.00', $order->fresh()->total);

        $this->add($order, $item);

        $order->refresh();
        $this->assertSame('1500.00', $order->subtotal);
        $this->assertSame('150.00', $order->discount);
        $this->assertSame('1350.00', $order->total);
    }

    public function test_service_charge_applies_to_dine_in_only(): void
    {
        config(['restaurant.pos.service_charge' => 10]);
        $item = $this->dish(['price' => '500.00']);

        $dineIn = $this->openBill();
        $this->add($dineIn, $item, 2);
        $this->assertSame('100.00', $dineIn->fresh()->service_charge);
        $this->assertSame('1100.00', $dineIn->fresh()->total);

        $this->actingAs($this->staff())->post(route('admin.pos.store'), ['type' => 'pickup'])->assertRedirect();
        $takeaway = Order::latest('id')->first();
        $this->add($takeaway, $item, 2);
        $this->assertSame('0.00', $takeaway->fresh()->service_charge);
        $this->assertSame('1000.00', $takeaway->fresh()->total);
    }

    // Kitchen

    public function test_the_kitchen_only_gets_what_is_new(): void
    {
        $item = $this->dish();
        $order = $this->openBill();
        $staff = $this->staff();
        $this->add($order, $item, 2);

        $this->actingAs($staff)->post(route('admin.pos.send', $order))
            ->assertOk()->assertSee('KITCHEN ORDER')->assertSee('2 x Kacchi Biryani')->assertSee('T1');

        $this->assertSame('preparing', $order->fresh()->status->value);
        $this->assertSame(2, $order->items()->first()->sent_qty);

        $this->actingAs($staff)->post(route('admin.pos.send', $order))->assertSessionHas('error'); // nothing new

        $this->add($order, $item);
        $this->actingAs($staff)->post(route('admin.pos.send', $order))
            ->assertOk()->assertSee('1 x Kacchi Biryani')->assertDontSee('3 x Kacchi Biryani');

        $this->actingAs($staff)->get(route('admin.pos.kot', $order))->assertSee('3 x Kacchi Biryani')->assertSee('REPRINT');
    }

    public function test_reducing_a_sent_item_is_logged(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish(), 2);
        $this->actingAs($this->staff())->post(route('admin.pos.send', $order));
        $line = $order->items()->first();

        $this->actingAs($this->staff())->patchJson(route('admin.pos.lines.update', [$order, $line]), ['quantity' => 1])->assertOk();

        $this->assertSame(1, $line->fresh()->sent_qty);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pos.void_item']);
    }

    // Payment

    public function test_a_cash_payment_closes_the_bill_and_frees_the_table(): void
    {
        $table = $this->table();
        $order = $this->openBill($table);
        $this->add($order, $this->dish(), 2);

        $this->pay($order, [['method' => 'cash', 'amount' => '760.00', 'tendered' => '1000']])->assertRedirectContains('/print/receipt');

        $order->refresh();
        $this->assertSame('completed', $order->status->value);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);

        $payment = $order->posPayments->first();
        $this->assertSame('760.00', $payment->amount);
        $this->assertSame('1000.00', $payment->tendered);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pos.paid']);

        $this->assertSame(760.0, app(AnalyticsService::class)->summary(now()->startOfDay(), now()->endOfDay())['revenue']);
        $this->openBill($table); // the table is free again
    }

    public function test_a_bill_can_be_split_between_methods(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish(), 2);

        $this->pay($order, [
            ['method' => 'cash', 'amount' => '400.00', 'tendered' => '500'],
            ['method' => 'card', 'amount' => '360.00', 'reference' => 'AUTH-77'],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('cash', $order->payment_method); // the larger part
        $this->assertSame(['cash', 'card'], $order->posPayments->pluck('method')->all());
        $this->assertSame('AUTH-77', $order->posPayments->last()->reference);
        $this->assertNull($order->posPayments->last()->tendered);
    }

    public function test_payments_must_add_up_to_the_bill(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish(), 2);

        $this->pay($order, [['method' => 'cash', 'amount' => '700.00']])->assertSessionHas('error');
        $this->pay($order, [['method' => 'cash', 'amount' => '800.00']])->assertSessionHas('error');
        $this->pay($order, [['method' => 'cash', 'amount' => '760.00', 'tendered' => '500']])->assertSessionHas('error');
        $this->pay($order, [['method' => 'crypto', 'amount' => '760.00']])->assertSessionHasErrors('payments.0.method');
        $this->pay($order, [])->assertSessionHasErrors('payments');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(0, OrderPayment::count());
    }

    public function test_an_empty_bill_cannot_be_paid_and_a_paid_bill_cannot_be_changed(): void
    {
        $order = $this->openBill();
        $this->pay($order, [['method' => 'cash', 'amount' => '10']])->assertSessionHas('error');

        $item = $this->dish();
        $this->add($order, $item);
        $this->pay($order, [['method' => 'cash', 'amount' => '380.00']])->assertRedirect();

        $this->add($order, $item)->assertStatus(422);
        $this->actingAs($this->staff())->postJson(route('admin.pos.discount', $order), ['type' => 'percent', 'value' => 5])->assertStatus(422);
        $this->pay($order, [['method' => 'cash', 'amount' => '380.00']])->assertSessionHas('error'); // already closed
        $this->actingAs($this->staff())->get(route('admin.pos.pay', $order))->assertRedirect(route('admin.pos.show', $order));
    }

    public function test_paying_takes_the_ingredients_and_saves_the_cost(): void
    {
        $rice = Ingredient::create(['name' => 'Rice', 'unit' => 'g', 'stock' => '1000', 'reorder_level' => '0', 'unit_cost' => '0.1000']);
        $item = $this->dish(['stock_mode' => 'recipe']);
        RecipeLine::create(['menu_item_id' => $item->id, 'ingredient_id' => $rice->id, 'quantity' => '300']);
        $order = $this->openBill();
        $this->add($order, $item, 2);

        $this->assertEquals(1000.0, (float) $rice->fresh()->stock); // nothing is taken before payment

        $this->pay($order, [['method' => 'cash', 'amount' => '760.00']])->assertRedirect();

        $this->assertEquals(400.0, (float) $rice->fresh()->stock);
        $this->assertSame('30.00', $order->items()->first()->unit_cost);
        $this->assertDatabaseHas('stock_movements', ['ingredient_id' => $rice->id, 'type' => 'sale', 'order_id' => $order->id]);
    }

    public function test_a_stock_shortage_keeps_the_bill_open(): void
    {
        $item = $this->dish(['stock_mode' => 'quantity', 'stock_qty' => 1]);
        $order = $this->openBill();
        $this->add($order, $item, 2);

        $this->pay($order, [['method' => 'cash', 'amount' => '760.00']])->assertSessionHas('error');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(1, $item->fresh()->stock_qty);
        $this->assertSame(0, OrderPayment::count());
    }

    // Cancel, void, move

    public function test_cancelling_a_bill(): void
    {
        $item = $this->dish();
        $empty = $this->openBill();
        $this->actingAs($this->staff())->post(route('admin.pos.cancel', $empty))->assertRedirect(route('admin.pos.index'));
        $this->assertSame('cancelled', $empty->fresh()->status->value);

        $sent = $this->openBill($this->table('T2'));
        $this->add($sent, $item);
        $this->actingAs($this->staff())->post(route('admin.pos.send', $sent));

        $this->actingAs($this->staff())->post(route('admin.pos.cancel', $sent))->assertSessionHas('error');
        $this->assertSame('preparing', $sent->fresh()->status->value);

        $this->actingAs($this->manager())->post(route('admin.pos.cancel', $sent))->assertRedirect();
        $this->assertSame('cancelled', $sent->fresh()->status->value);
    }

    public function test_a_manager_can_void_a_paid_bill(): void
    {
        $rice = Ingredient::create(['name' => 'Rice', 'unit' => 'g', 'stock' => '1000', 'reorder_level' => '0', 'unit_cost' => '0.1000']);
        $item = $this->dish(['stock_mode' => 'recipe']);
        RecipeLine::create(['menu_item_id' => $item->id, 'ingredient_id' => $rice->id, 'quantity' => '300']);
        $order = $this->openBill();
        $this->add($order, $item, 2);
        $this->pay($order, [['method' => 'cash', 'amount' => '760.00']]);
        $this->assertEquals(400.0, (float) $rice->fresh()->stock);

        $this->actingAs($this->staff())->post(route('admin.pos.void', $order), ['reason' => 'Wrong table'])->assertForbidden();
        $this->actingAs($this->manager())->post(route('admin.pos.void', $order), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->manager())->post(route('admin.pos.void', $order), ['reason' => 'Wrong table'])->assertRedirect(route('admin.pos.index'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertEquals(0.0, (float) $order->posPayments->sum('amount'));       // the payment and its reversal cancel out
        $this->assertEquals(1000.0, (float) $rice->fresh()->stock);                 // stock is back
        $this->assertSame(0.0, app(AnalyticsService::class)->summary(now()->startOfDay(), now()->endOfDay())['revenue']);

        $this->actingAs($this->manager())->post(route('admin.pos.void', $order), ['reason' => 'Again'])->assertSessionHas('error');
    }

    public function test_a_bill_can_move_to_another_table(): void
    {
        $order = $this->openBill($this->table('T1'));
        $t2 = $this->table('T2');
        $busy = $this->table('T3');
        $this->openBill($busy);
        $staff = $this->staff();

        $this->actingAs($staff)->patch(route('admin.pos.update', $order), ['dining_table_id' => $busy->id])->assertSessionHas('error');
        $this->actingAs($staff)->patch(route('admin.pos.update', $order), ['dining_table_id' => $t2->id, 'notes' => 'No chili', 'name' => 'Rahim'])->assertSessionHas('status');

        $order->refresh();
        $this->assertSame('T2', $order->table_name);
        $this->assertSame('No chili', $order->notes);
        $this->assertSame('Rahim', $order->name);
    }

    // Cash drawer

    public function test_the_cash_drawer_compares_expected_and_counted_cash(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.pos.shift.open'), ['opening_cash' => '1000'])->assertRedirect();
        $this->actingAs($staff)->post(route('admin.pos.shift.open'), ['opening_cash' => '500'])->assertSessionHas('error');

        $order = $this->openBill();
        $this->add($order, $this->dish(), 2);
        $this->pay($order, [['method' => 'cash', 'amount' => '600.00'], ['method' => 'card', 'amount' => '160.00']]);

        $session = CashSession::firstOrFail();
        $this->assertSame($session->id, $order->posPayments->first()->cash_session_id);
        $this->actingAs($staff)->get(route('admin.pos.shift'))->assertOk()->assertSee('Should be in the drawer');

        $this->actingAs($staff)->post(route('admin.pos.shift.close'), ['counted_cash' => '1590'])->assertRedirect();

        $session->refresh();
        $this->assertSame('1600.00', $session->expected_cash);   // 1000 + 600 cash (the card part is not cash)
        $this->assertSame('1590.00', $session->counted_cash);
        $this->assertNotNull($session->closed_at);

        $this->actingAs($staff)->post(route('admin.pos.shift.close'), ['counted_cash' => '1'])->assertSessionHas('error');
        $this->actingAs($staff)->get(route('admin.pos.shift'))->assertSee('-');

        // Money taken after the shift is closed does not count against it.
        $next = $this->openBill($this->table('T9'));
        $this->add($next, $this->dish());
        $this->pay($next, [['method' => 'cash', 'amount' => '380.00']]);
        $this->assertNull($next->posPayments->first()->cash_session_id);
    }

    // Receipt, refresh signal, reports

    public function test_the_receipt_shows_the_table_and_the_payments(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish(), 2);
        $this->pay($order, [['method' => 'cash', 'amount' => '760.00', 'tendered' => '1000']]);

        $this->actingAs($this->staff())->get(route('admin.orders.print', ['order' => $order, 'type' => 'receipt']))
            ->assertOk()->assertSee('Table')->assertSee('T1')->assertSee('Dine in')->assertSee('Served by')
            ->assertSee('Cash given')->assertSee('৳ 240')->assertSee('Back to POS');
    }

    public function test_other_screens_are_told_when_the_tables_change(): void
    {
        $staff = $this->staff();
        $before = $this->actingAs($staff)->getJson(route('admin.pos.state'))->assertOk()->json('signature');

        $order = $this->openBill();
        $middle = $this->actingAs($staff)->getJson(route('admin.pos.state'))->json('signature');
        $this->add($order, $this->dish());
        $after = $this->actingAs($staff)->getJson(route('admin.pos.state'))->json('signature');

        $this->assertSame('', $before);
        $this->assertNotSame($before, $middle);
        $this->assertNotSame($middle, $after);
    }

    public function test_restaurant_bills_show_in_the_orders_list_and_reports(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish());
        $this->pay($order, [['method' => 'cash', 'amount' => '380.00']]);
        Order::factory()->create(['created_at' => now(), 'total' => '100.00']); // an online cash order

        $this->actingAs($this->manager())->get('/admin/orders')->assertOk()->assertSee('Dine in')->assertSee('POS');
        $this->actingAs($this->manager())->get(route('admin.orders.show', $order))->assertOk()->assertSee('Dine in');

        $breakdown = app(AnalyticsService::class)->breakdown(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(1, $breakdown['source']['pos']['orders']);
        $this->assertSame(1, $breakdown['source']['online']['orders']);
        $this->assertSame('online', Order::whereNot('source', 'pos')->first()->source);
    }

    public function test_walk_ins_without_a_phone_are_not_counted_as_customers(): void
    {
        $order = $this->openBill();
        $this->add($order, $this->dish());
        $this->pay($order, [['method' => 'cash', 'amount' => '380.00']]);

        $named = $this->openBill($this->table('T2'));
        $this->actingAs($this->staff())->patch(route('admin.pos.update', $named), ['phone' => '01711111111', 'name' => 'Rahim']);
        $this->add($named, $this->dish());
        $this->pay($named, [['method' => 'cash', 'amount' => '380.00']]);

        $report = app(AnalyticsService::class)->customerReport(now()->startOfDay(), now()->endOfDay(), 'daily');

        $this->assertSame(1, $report['summary']['total']);
        $this->assertSame('Rahim', $report['top'][0]['name']);
    }
}
