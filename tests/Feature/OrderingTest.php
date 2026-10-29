<?php

namespace Tests\Feature;

use App\Mail\NewOrderAlertMail;
use App\Mail\OrderUpdateMail;
use App\Models\Coupon;
use App\Models\FoodAddon;
use App\Models\FoodVariation;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge(['name_en' => 'Kacchi Biryani', 'price' => '380.00'], $attributes));
    }

    private function add(MenuItem $item, int $quantity = 1, array $extra = [])
    {
        return $this->postJson('/cart/items', array_merge(['item_id' => $item->id, 'quantity' => $quantity], $extra));
    }

    private function checkout(array $overrides = [])
    {
        return $this->postJson('/checkout', array_merge([
            'type' => 'delivery',
            'name' => 'Rahim Uddin',
            'phone' => '01712345678',
            'email' => 'rahim@example.com',
            'address' => 'House 1, Road 2',
            'payment_method' => 'cod',
        ], $overrides));
    }

    // Cart

    public function test_items_can_be_added_and_the_cart_shows_server_prices(): void
    {
        $item = $this->item();

        $this->add($item, 2)->assertOk()->assertJson(['count' => 2]);

        $this->get('/cart')->assertOk()->assertSee('Kacchi Biryani')->assertSee('৳ 380')
            ->assertViewHas('subtotal', 76000);
    }

    public function test_prices_sent_by_the_browser_are_ignored(): void
    {
        $item = $this->item();

        $this->add($item, 1, ['price' => '1', 'unit_price' => 1, 'total' => 1])->assertOk();

        $this->get('/cart')->assertViewHas('subtotal', 38000)->assertDontSee('৳ 1<', false);
    }

    public function test_unavailable_hidden_and_unknown_items_cannot_be_added(): void
    {
        $soldOut = $this->item(['slug' => 'sold-out']);
        $soldOut->update(['is_available' => false]);
        $soldOut->save();
        $hidden = MenuItem::factory()->create(['menu_category_id' => MenuCategory::factory()->inactive()->create()->id]);

        $this->add($soldOut)->assertStatus(422)->assertJsonValidationErrors('item_id');
        $this->add($hidden)->assertStatus(422)->assertJsonValidationErrors('item_id');
        $this->postJson('/cart/items', ['item_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('item_id');
        $this->postJson('/cart/items', [])->assertStatus(422)->assertJsonValidationErrors('item_id');

        $this->get('/cart')->assertViewHas('count', 0);
    }

    public function test_quantity_limits(): void
    {
        $item = $this->item();

        $this->add($item, 0)->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->add($item, 21)->assertStatus(422)->assertJsonValidationErrors('quantity');

        $this->add($item, 15)->assertOk();
        $this->add($item, 15)->assertOk()->assertJson(['count' => 20]); // capped, not 30
    }

    public function test_quantity_can_be_changed_and_lines_removed(): void
    {
        $item = $this->item();
        $this->add($item, 1);
        $line = CartService::lineKey($item->id, null, []);

        $this->patchJson(route('cart.update', ['line' => $line]), ['quantity' => 3])->assertOk()->assertJson(['count' => 3]);
        $this->patchJson(route('cart.update', ['line' => $line]), ['quantity' => 99])->assertStatus(422);
        $this->patchJson(route('cart.update', ['line' => $line]), ['quantity' => 0])->assertOk()->assertJson(['count' => 0]);

        $this->add($item, 2);
        $this->deleteJson(route('cart.remove', ['line' => $line]))->assertOk()->assertJson(['count' => 0]);

        $this->patchJson('/cart/items/abc', ['quantity' => 1])->assertNotFound();
        $this->patchJson(route('cart.update', ['line' => '999_0_']), ['quantity' => 1])->assertOk(); // unknown line: harmless
    }

    // Checkout

    public function test_cash_delivery_order_is_priced_on_the_server(): void
    {
        $item = $this->item();
        $this->add($item);

        $response = $this->checkout(['total' => 1, 'subtotal' => 1, 'delivery_fee' => 0, 'status' => 'completed', 'payment_status' => 'paid'])
            ->assertCreated();

        $order = Order::with('items')->firstOrFail();
        $response->assertJsonPath('redirect', route('orders.show', $order->token));

        $this->assertSame('380.00', $order->subtotal);
        $this->assertSame('60.00', $order->delivery_fee);
        $this->assertSame('440.00', $order->total);
        $this->assertSame('pending', $order->status->value);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(40, strlen($order->token));

        $line = $order->items->first();
        $this->assertSame('Kacchi Biryani', $line->name_en);
        $this->assertSame('380.00', $line->unit_price);
        $this->assertSame(1, $line->quantity);

        Mail::assertQueued(NewOrderAlertMail::class);
        Mail::assertQueued(OrderUpdateMail::class);
        $this->get('/cart')->assertViewHas('count', 0);
    }

    public function test_pickup_has_no_delivery_fee_and_drops_the_address(): void
    {
        $this->add($this->item());

        $this->checkout(['type' => 'pickup', 'address' => 'Should be ignored'])->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('380.00', $order->total);
        $this->assertNull($order->address);
    }

    public function test_delivery_is_free_above_the_threshold(): void
    {
        $this->add($this->item(), 4); // 1520

        $this->checkout()->assertCreated();

        $this->assertSame('0.00', Order::firstOrFail()->delivery_fee);
        $this->assertSame('1520.00', Order::firstOrFail()->total);
    }

    public function test_checkout_validation(): void
    {
        $this->add($this->item());

        $this->checkout(['address' => ''])->assertStatus(422)->assertJsonValidationErrors('address');
        $this->checkout(['name' => ''])->assertJsonValidationErrors('name');
        $this->checkout(['phone' => 'abc'])->assertJsonValidationErrors('phone');
        $this->checkout(['email' => 'nope'])->assertJsonValidationErrors('email');
        $this->checkout(['type' => 'teleport'])->assertJsonValidationErrors('type');
        $this->checkout(['payment_method' => 'bitcoin'])->assertJsonValidationErrors('payment_method');
        $this->checkout(['website' => 'spam'])->assertJsonValidationErrors('website');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_online_payment_can_only_be_chosen_when_it_is_enabled(): void
    {
        $this->add($this->item());

        $this->checkout(['payment_method' => 'sslcommerz'])->assertStatus(422)->assertJsonValidationErrors('payment_method');

        config(['restaurant.ordering.online_payment' => true]);
        $this->checkout(['payment_method' => 'sslcommerz'])->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('sslcommerz', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        Mail::assertNotQueued(NewOrderAlertMail::class); // the kitchen is told only once it is paid
    }

    public function test_minimum_order_amount(): void
    {
        $this->add($this->item(['price' => '100.00']));

        $this->get('/checkout')->assertRedirect(route('cart.show'));
        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_cart_cannot_be_checked_out(): void
    {
        $this->get('/checkout')->assertRedirect(route('cart.show'));
        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('cart');
    }

    public function test_item_that_becomes_unavailable_blocks_the_order(): void
    {
        $item = $this->item();
        $this->add($item);

        $item->update(['is_available' => false]);

        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/cart')->assertViewHas('count', 0);
    }

    public function test_price_changes_after_adding_are_picked_up(): void
    {
        $item = $this->item();
        $this->add($item);

        $item->update(['price' => '400.00']);
        $this->checkout()->assertCreated();

        $order = Order::with('items')->firstOrFail();
        $this->assertSame('400.00', $order->items->first()->unit_price);
        $this->assertSame('460.00', $order->total);
    }

    public function test_orders_keep_their_prices_when_the_menu_changes_later(): void
    {
        $item = $this->item();
        $this->add($item);
        $this->checkout()->assertCreated();

        $item->update(['price' => '999.00', 'name_en' => 'Renamed']);

        $line = Order::with('items')->firstOrFail()->items->first();
        $this->assertSame('380.00', $line->unit_price);
        $this->assertSame('Kacchi Biryani', $line->name_en);
    }

    public function test_ordering_can_be_switched_off(): void
    {
        $item = $this->item();
        $this->get('/menu')->assertSee('data-cart-add', false);

        config(['restaurant.ordering_enabled' => false]);

        $this->get('/cart')->assertNotFound();
        $this->get('/checkout')->assertNotFound();
        $this->postJson('/cart/items', ['item_id' => $item->id])->assertNotFound();
        $this->get('/menu')->assertDontSee('data-cart-add', false);
        $this->get('/orders/track')->assertOk(); // tracking keeps working
    }

    // Coupons

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::factory()->create(array_merge(['code' => 'WELCOME10', 'type' => 'percent', 'value' => '10'], $attributes));
    }

    public function test_coupon_discount_is_applied_and_counted(): void
    {
        $coupon = $this->coupon(['min_subtotal' => '300']);
        $this->add($this->item());

        $this->postJson('/cart/coupon', ['code' => ' welcome10 '])->assertOk()->assertJsonStructure(['html', 'message']);
        $this->get('/cart')->assertViewHas('discount', 3800);

        $this->checkout()->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('38.00', $order->discount);
        $this->assertSame('WELCOME10', $order->coupon_code);
        $this->assertSame('402.00', $order->total); // 380 - 38 + 60
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_coupon_caps_and_fixed_amounts(): void
    {
        $this->coupon(['code' => 'HALF', 'value' => '50', 'max_discount' => '100']);
        $this->coupon(['code' => 'BIG', 'type' => 'fixed', 'value' => '1000']);
        $this->add($this->item());

        $this->postJson('/cart/coupon', ['code' => 'HALF'])->assertOk();
        $this->get('/cart')->assertViewHas('discount', 10000);

        $this->postJson('/cart/coupon', ['code' => 'BIG'])->assertOk();
        $this->get('/cart')->assertViewHas('discount', 38000); // never more than the subtotal
    }

    public function test_bad_coupons_are_rejected(): void
    {
        $this->add($this->item());

        $this->postJson('/cart/coupon', ['code' => 'NOPE'])->assertStatus(422)->assertJsonValidationErrors('code');

        foreach (
            [
                ['is_active' => false],
                ['ends_at' => now()->subMinute()],
                ['starts_at' => now()->addDay()],
                ['usage_limit' => 1, 'used_count' => 1],
                ['min_subtotal' => '500'],
            ] as $i => $attributes
        ) {
            $this->coupon(['code' => "BAD{$i}"] + $attributes);
            $this->postJson('/cart/coupon', ['code' => "BAD{$i}"])->assertStatus(422)->assertJsonValidationErrors('code');
        }

        $this->get('/cart')->assertViewHas('discount', 0);
    }

    public function test_coupon_is_dropped_when_the_cart_no_longer_qualifies(): void
    {
        $this->coupon(['min_subtotal' => '300']);
        $big = $this->item();
        $small = $this->item(['name_en' => 'Cheap Dish', 'price' => '250.00']);

        $this->add($big);
        $this->postJson('/cart/coupon', ['code' => 'WELCOME10'])->assertOk();

        $this->deleteJson(route('cart.remove', ['line' => CartService::lineKey($big->id, null, [])]));
        $this->add($small);

        $this->get('/cart')->assertViewHas('discount', 0)->assertViewHas('coupon', null);
    }

    public function test_coupon_can_be_removed(): void
    {
        $this->coupon();
        $this->add($this->item());
        $this->postJson('/cart/coupon', ['code' => 'WELCOME10'])->assertOk();

        $this->deleteJson('/cart/coupon')->assertOk();

        $this->get('/cart')->assertViewHas('discount', 0);
    }

    public function test_last_redemption_cannot_be_used_twice(): void
    {
        $coupon = $this->coupon(['usage_limit' => 1]);
        $this->add($this->item());
        $this->postJson('/cart/coupon', ['code' => 'WELCOME10'])->assertOk();

        // Someone else uses it between applying and paying.
        $coupon->update(['used_count' => 1]);

        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    // Sizes and extras

    public function test_item_with_sizes_requires_a_valid_size(): void
    {
        $item = $this->item(['price' => '300.00']);
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Half', 'price' => '250.00']);
        $inactive = FoodVariation::factory()->inactive()->create(['menu_item_id' => $item->id, 'name_en' => 'Gone']);
        $other = FoodVariation::factory()->create();

        $this->add($item)->assertStatus(422)->assertJsonValidationErrors('variation_id');
        $this->add($item, 1, ['variation_id' => $inactive->id])->assertStatus(422)->assertJsonValidationErrors('variation_id');
        $this->add($item, 1, ['variation_id' => $other->id])->assertStatus(422)->assertJsonValidationErrors('variation_id');

        $this->add($item, 1, ['variation_id' => $half->id])->assertOk();
        $this->get('/cart')->assertViewHas('subtotal', 25000); // the size price replaces the base price
    }

    public function test_extras_are_added_to_the_price_and_must_belong_to_the_item(): void
    {
        $item = $this->item(['price' => '300.00']);
        $egg = FoodAddon::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Egg', 'price' => '30.00']);
        $salad = FoodAddon::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Salad', 'price' => '40.00']);
        $foreign = FoodAddon::factory()->create();

        $this->add($item, 1, ['addons' => [$foreign->id]])->assertStatus(422)->assertJsonValidationErrors('addons');
        $this->add($item, 1, ['addons' => [$egg->id, 9999]])->assertStatus(422)->assertJsonValidationErrors('addons');

        $this->add($item, 2, ['addons' => [$egg->id, $salad->id]])->assertOk();
        $this->get('/cart')->assertViewHas('subtotal', 2 * 37000); // (300 + 30 + 40) x 2
    }

    public function test_identical_selections_merge_and_different_ones_stay_apart(): void
    {
        $item = $this->item();
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Half', 'price' => '250.00']);
        $full = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Full', 'price' => '380.00']);
        $egg = FoodAddon::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Egg']);

        $this->add($item, 1, ['variation_id' => $half->id, 'addons' => [$egg->id]]);
        $this->add($item, 1, ['variation_id' => $half->id, 'addons' => [$egg->id]]);
        $this->get('/cart')->assertViewHas('lines', fn($lines) => $lines->count() === 1 && $lines->first()['qty'] === 2);

        $this->add($item, 1, ['variation_id' => $full->id]);
        $this->get('/cart')->assertViewHas('lines', fn($lines) => $lines->count() === 2);
    }

    public function test_option_choices_are_snapshotted_on_the_order(): void
    {
        $item = $this->item();
        $full = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Full', 'name_bn' => 'ফুল', 'price' => '380.00']);
        $egg = FoodAddon::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Egg', 'price' => '30.00']);

        $this->add($item, 2, ['variation_id' => $full->id, 'addons' => [$egg->id]]);
        $this->checkout(['type' => 'pickup'])->assertCreated();

        $full->update(['price' => '999.00', 'name_en' => 'Jumbo']);
        $egg->delete();

        $line = Order::with('items')->firstOrFail()->items->first();
        $this->assertSame('Full', $line->variation_en);
        $this->assertSame('ফুল', $line->variation_bn);
        $this->assertSame('Egg', $line->addons[0]['name_en']);
        $this->assertSame('410.00', $line->unit_price);
        $this->assertSame('820.00', $line->line_total);
        $this->assertSame('Full, + Egg', $line->optionsLabel('en'));
    }

    public function test_deactivated_option_removes_the_line_from_the_cart(): void
    {
        $item = $this->item();
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Half', 'price' => '250.00']);
        $this->add($item, 1, ['variation_id' => $half->id]);

        $half->update(['is_active' => false]);

        $this->get('/cart')->assertViewHas('count', 0)->assertViewHas('removed', ['Kacchi Biryani']);
        $this->checkout()->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }
}
