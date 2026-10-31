<?php

namespace Tests\Feature;

use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function zone(array $attributes = []): DeliveryZone
    {
        return DeliveryZone::create(array_merge([
            'name_en' => 'Gulshan',
            'name_bn' => 'গুলশান',
            'fee' => '80.00',
            'sort_order' => 1,
            'is_active' => true,
        ], $attributes));
    }

    private function addItem(string $price = '500.00', int $quantity = 1): MenuItem
    {
        $item = MenuItem::factory()->create(['price' => $price]);
        $this->postJson('/cart/items', ['item_id' => $item->id, 'quantity' => $quantity])->assertOk();

        return $item;
    }

    private function checkout(array $overrides = [])
    {
        return $this->postJson('/checkout', array_merge([
            'type' => 'delivery',
            'name' => 'Rahim',
            'phone' => '01712345678',
            'address' => 'House 1, Road 2',
            'payment_method' => 'cod',
        ], $overrides));
    }

    // Without areas the old rules apply

    public function test_without_areas_the_general_fee_applies_and_no_area_is_asked_for(): void
    {
        $this->addItem();

        $this->checkout()->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('60.00', $order->delivery_fee);
        $this->assertNull($order->delivery_zone_id);
        $this->assertNull($order->zone_en);
    }

    public function test_inactive_areas_are_ignored(): void
    {
        $this->zone(['is_active' => false]);
        $this->addItem();

        $this->checkout()->assertCreated();
        $this->assertSame('60.00', Order::firstOrFail()->delivery_fee);
    }

    // With areas

    public function test_an_area_is_required_for_delivery(): void
    {
        $this->zone();
        $this->addItem();

        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('delivery_zone_id');
        $this->checkout(['delivery_zone_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('delivery_zone_id');
        $this->checkout(['delivery_zone_id' => $this->zone(['name_en' => 'Closed', 'is_active' => false])->id])->assertStatus(422)->assertJsonValidationErrors('delivery_zone_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_fee_comes_from_the_area_and_the_area_is_saved_on_the_order(): void
    {
        $zone = $this->zone(['fee' => '80.00']);
        $this->addItem('500.00');

        $this->checkout(['delivery_zone_id' => $zone->id])->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('80.00', $order->delivery_fee);
        $this->assertSame('580.00', $order->total);
        $this->assertSame($zone->id, $order->delivery_zone_id);
        $this->assertSame('Gulshan', $order->zone_en);
        $this->assertSame('গুলশান', $order->zone_bn);
    }

    public function test_the_order_keeps_the_area_name_when_the_area_changes_later(): void
    {
        $zone = $this->zone();
        $this->addItem();
        $this->checkout(['delivery_zone_id' => $zone->id])->assertCreated();

        $zone->update(['name_en' => 'Renamed', 'fee' => '999.00']);
        $zone->delete();

        $order = Order::firstOrFail();
        $this->assertSame('Gulshan', $order->zone_en);
        $this->assertSame('80.00', $order->delivery_fee);
        $this->assertNull($order->delivery_zone_id); // the link goes, the saved name stays
    }

    public function test_pickup_ignores_areas(): void
    {
        $this->zone();
        $this->addItem();

        $this->checkout(['type' => 'pickup', 'address' => null])->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertNull($order->zone_en);
    }

    public function test_a_price_sent_by_the_browser_is_ignored(): void
    {
        $zone = $this->zone(['fee' => '80.00']);
        $this->addItem('500.00');

        $this->checkout(['delivery_zone_id' => $zone->id, 'delivery_fee' => 0, 'total' => 1])->assertCreated();

        $this->assertSame('80.00', Order::firstOrFail()->delivery_fee);
    }

    public function test_the_area_free_delivery_limit_overrides_the_general_one(): void
    {
        $zone = $this->zone(['fee' => '80.00', 'free_over' => '400.00']); // general limit is 1500
        $this->addItem('500.00');

        $this->checkout(['delivery_zone_id' => $zone->id])->assertCreated();

        $this->assertSame('0.00', Order::firstOrFail()->delivery_fee);
    }

    public function test_a_zero_limit_means_never_free_in_that_area(): void
    {
        $zone = $this->zone(['fee' => '80.00', 'free_over' => '0.00']);
        $this->addItem('2000.00'); // would be free under the general rule

        $this->checkout(['delivery_zone_id' => $zone->id])->assertCreated();

        $this->assertSame('80.00', Order::firstOrFail()->delivery_fee);
    }

    public function test_an_empty_limit_falls_back_to_the_general_rule(): void
    {
        $zone = $this->zone(['fee' => '80.00', 'free_over' => null]);
        $this->addItem('1600.00'); // above the general 1500 limit

        $this->checkout(['delivery_zone_id' => $zone->id])->assertCreated();

        $this->assertSame('0.00', Order::firstOrFail()->delivery_fee);
    }

    public function test_the_area_minimum_order_is_enforced(): void
    {
        $zone = $this->zone(['min_order' => '600.00']);
        $this->addItem('500.00'); // fine for the general minimum (200), not for this area

        $this->checkout(['delivery_zone_id' => $zone->id])->assertStatus(422)->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);

        $this->checkout(['type' => 'pickup', 'address' => null])->assertCreated(); // pickup is not affected
    }

    public function test_an_empty_area_minimum_uses_the_general_minimum(): void
    {
        $zone = $this->zone(['min_order' => null]);
        $this->addItem('250.00');

        $this->checkout(['delivery_zone_id' => $zone->id])->assertCreated();
    }

    // The pages

    public function test_the_checkout_page_offers_the_areas_with_their_totals(): void
    {
        $gulshan = $this->zone(['fee' => '80.00', 'min_order' => '600.00']);
        $free = $this->zone(['name_en' => 'Banani', 'fee' => '0.00', 'sort_order' => 2]);
        $this->addItem('500.00');

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('name="delivery_zone_id"', false)
            ->assertSee('data-zones', false)
            ->assertSee('Gulshan (৳ 80)')
            ->assertSee('Banani (Free)')
            ->assertViewHas('zoneTotals', fn($z) => $z[$gulshan->id]['fee'] === '৳ 80'
                && $z[$gulshan->id]['total'] === '৳ 580'
                && str_contains($z[$gulshan->id]['note'], '৳ 600')
                && $z[$free->id]['fee'] === 'Free'
                && $z[$free->id]['note'] === '');
    }

    public function test_the_checkout_page_has_no_area_box_when_there_are_no_areas(): void
    {
        $this->addItem();

        $this->get('/checkout')->assertOk()->assertDontSee('name="delivery_zone_id"', false)->assertDontSee('data-zones', false);
    }

    public function test_the_area_shows_on_the_order_pages_in_the_right_language(): void
    {
        $order = Order::factory()->create(['zone_en' => 'Gulshan', 'zone_bn' => 'গুলশান']);
        $staff = User::factory()->staff()->create();

        $this->get(route('orders.show', $order->token))->assertSee('Gulshan')->assertDontSee('গুলশান');
        $this->get('/lang/bn');
        $this->get(route('orders.show', $order->token))->assertSee('গুলশান');

        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertSee('Delivery area')->assertSee('Gulshan');
        $this->actingAs($staff)->get(route('admin.orders.print', ['order' => $order, 'type' => 'receipt']))->assertSee('Area: Gulshan');
    }

    // Admin

    public function test_a_manager_manages_the_areas(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('admin.content.store', 'zones'), [
            'name_en' => 'Dhanmondi',
            'name_bn' => 'ধানমন্ডি',
            'fee' => '70',
            'min_order' => '300',
            'free_over' => '',
            'is_active' => 1,
        ])->assertRedirect();

        $zone = DeliveryZone::firstOrFail();
        $this->assertSame('70.00', $zone->fee);
        $this->assertSame('300.00', $zone->min_order);
        $this->assertNull($zone->free_over);

        $this->actingAs($manager)->get('/admin/content/zones')->assertOk()->assertSee('Dhanmondi');
        $this->actingAs($manager)->delete(route('admin.content.destroy', ['zones', $zone->id]))->assertRedirect();
        $this->assertModelMissing($zone);
    }

    public function test_area_validation_and_access(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('admin.content.store', 'zones'), ['fee' => '-5', 'is_active' => 1])
            ->assertSessionHasErrors(['name_en', 'fee']);
        $this->actingAs($manager)->post(route('admin.content.store', 'zones'), ['name_en' => 'X', 'fee' => '10.555', 'is_active' => 1])
            ->assertSessionHasErrors('fee');

        $this->actingAs(User::factory()->staff()->create())->get('/admin/content/zones')->assertForbidden();
        $this->assertDatabaseCount('delivery_zones', 0);
    }
}
