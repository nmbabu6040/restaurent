<?php

namespace Tests\Feature;

use App\Models\FoodAddon;
use App\Models\FoodVariation;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RecipeLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function add(MenuItem $item, int $quantity = 1, array $extra = [])
    {
        return $this->postJson('/cart/items', array_merge(['item_id' => $item->id, 'quantity' => $quantity], $extra));
    }

    private function checkout()
    {
        return $this->postJson('/checkout', ['type' => 'pickup', 'name' => 'Rahim', 'phone' => '01712345678', 'payment_method' => 'cod']);
    }

    private function ingredient(string $name, string $stock, string $cost = '0.1000', array $extra = []): Ingredient
    {
        return Ingredient::create(array_merge(['name' => $name, 'unit' => 'g', 'stock' => $stock, 'reorder_level' => '0', 'unit_cost' => $cost], $extra));
    }

    private function recipeItem(array $lines, array $attributes = []): MenuItem
    {
        $item = MenuItem::factory()->create(array_merge(['price' => '400.00', 'stock_mode' => 'recipe'], $attributes));

        foreach ($lines as [$ingredient, $quantity]) {
            RecipeLine::create(['menu_item_id' => $item->id, 'ingredient_id' => $ingredient->id, 'quantity' => $quantity]);
        }

        return $item;
    }

    private function stock(Ingredient $ingredient): float
    {
        return (float) $ingredient->fresh()->stock;
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    // Dish quantity mode

    public function test_dish_stock_is_checked_taken_and_hides_the_dish_at_zero(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'stock_mode' => 'quantity', 'stock_qty' => 3]);

        $this->add($item, 4)->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->add($item, 3)->assertOk();
        $this->checkout()->assertCreated();

        $item->refresh();
        $this->assertSame(0, $item->stock_qty);
        $this->assertFalse($item->is_available);
        $this->add($item)->assertStatus(422)->assertJsonValidationErrors('item_id');
    }

    public function test_cart_total_across_sizes_counts_against_dish_stock(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'stock_mode' => 'quantity', 'stock_qty' => 3]);
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'price' => '200.00']);
        $full = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'price' => '300.00']);

        $this->add($item, 2, ['variation_id' => $half->id])->assertOk();
        $this->add($item, 2, ['variation_id' => $full->id])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_a_shortage_found_at_checkout_blocks_the_order_and_changes_nothing(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'stock_mode' => 'quantity', 'stock_qty' => 5]);
        $this->add($item, 2)->assertOk();

        $item->update(['stock_qty' => 1]); // someone else bought the rest

        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $item->fresh()->stock_qty);
    }

    public function test_cancelling_gives_dish_stock_back_once(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'stock_mode' => 'quantity', 'stock_qty' => 3]);
        $this->add($item, 3);
        $this->checkout()->assertCreated();
        $this->assertFalse($item->fresh()->is_available);

        DB::transaction(fn() => Order::firstOrFail()->markCancelled());

        $this->assertSame(3, $item->fresh()->stock_qty);
        $this->assertTrue($item->fresh()->is_available);

        DB::transaction(fn() => Order::firstOrFail()->markCancelled()); // a second call must not add more
        $this->assertSame(3, $item->fresh()->stock_qty);
    }

    public function test_items_without_stock_tracking_are_never_limited(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'stock_mode' => 'none', 'stock_qty' => 0]);

        $this->add($item, 10)->assertOk();
        $this->checkout()->assertCreated();
        $this->assertTrue($item->fresh()->is_available);
    }

    // Recipe mode

    public function test_recipe_orders_take_ingredients_scaled_by_size_and_quantity(): void
    {
        $rice = $this->ingredient('Rice', '10000', '0.1000');
        $mutton = $this->ingredient('Mutton', '5000', '0.8000');
        $item = $this->recipeItem([[$rice, '300'], [$mutton, '250']]);
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Half', 'price' => '250.00', 'portion_factor' => '0.60']);
        $full = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Full', 'price' => '400.00', 'portion_factor' => '1.00']);

        $this->add($item, 2, ['variation_id' => $full->id])->assertOk();
        $this->add($item, 1, ['variation_id' => $half->id])->assertOk();
        $this->checkout()->assertCreated();

        // rice: 300 x 2 + 180 = 780, mutton: 250 x 2 + 150 = 650
        $this->assertEqualsWithDelta(9220.0, $this->stock($rice), 0.001);
        $this->assertEqualsWithDelta(4350.0, $this->stock($mutton), 0.001);

        $order = Order::with('items')->firstOrFail();
        $this->assertSame(2, StockMovement::where('type', 'sale')->where('order_id', $order->id)->count());
        $this->assertEqualsWithDelta(-780.0, (float) StockMovement::where('ingredient_id', $rice->id)->value('quantity'), 0.001);

        // cost snapshot: full = 300 x 0.1 + 250 x 0.8 = 230, half = 60% of that = 138
        $this->assertSame('230.00', $order->items->firstWhere('variation_en', 'Full')->unit_cost);
        $this->assertSame('138.00', $order->items->firstWhere('variation_en', 'Half')->unit_cost);
    }

    public function test_recipe_orders_are_not_blocked_by_default_and_stock_can_go_negative(): void
    {
        $rice = $this->ingredient('Rice', '100');
        $item = $this->recipeItem([[$rice, '300']]);

        $this->add($item)->assertOk();
        $this->checkout()->assertCreated();

        $this->assertEqualsWithDelta(-200.0, $this->stock($rice), 0.001);
        $this->assertSame(1, Ingredient::low()->count());
    }

    public function test_recipe_orders_can_be_blocked_when_ingredients_run_short(): void
    {
        config(['restaurant.inventory.enforce_recipe_stock' => true]);
        $rice = $this->ingredient('Rice', '100');
        $item = $this->recipeItem([[$rice, '300']], ['name_en' => 'Kacchi']);

        $this->add($item)->assertOk();
        $this->checkout()->assertStatus(422)->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEqualsWithDelta(100.0, $this->stock($rice), 0.001);
    }

    public function test_cancelling_returns_ingredients_once(): void
    {
        $rice = $this->ingredient('Rice', '1000');
        $item = $this->recipeItem([[$rice, '300']]);
        $this->add($item, 2);
        $this->checkout()->assertCreated();
        $this->assertEqualsWithDelta(400.0, $this->stock($rice), 0.001);

        DB::transaction(fn() => Order::firstOrFail()->markCancelled());
        $this->assertEqualsWithDelta(1000.0, $this->stock($rice), 0.001);
        $this->assertSame(1, StockMovement::where('type', 'return')->count());

        DB::transaction(fn() => Order::firstOrFail()->markCancelled());
        $this->assertEqualsWithDelta(1000.0, $this->stock($rice), 0.001);
    }

    // Cost snapshots

    public function test_cost_uses_item_cost_with_size_override_and_addon_cost(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'cost_price' => '120.00']);
        $size = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'price' => '250.00', 'cost_price' => '90.00']);
        $addon = FoodAddon::factory()->create(['menu_item_id' => $item->id, 'price' => '30.00', 'cost_price' => '10.00']);

        $this->add($item, 1, ['variation_id' => $size->id, 'addons' => [$addon->id]])->assertOk();
        $this->checkout()->assertCreated();

        $this->assertSame('100.00', Order::with('items')->firstOrFail()->items->first()->unit_cost); // 90 + 10
    }

    public function test_a_size_without_its_own_cost_uses_the_item_cost(): void
    {
        $item = MenuItem::factory()->create(['price' => '300.00', 'cost_price' => '120.00']);
        $size = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'price' => '250.00', 'cost_price' => null]);

        $this->add($item, 1, ['variation_id' => $size->id]);
        $this->checkout()->assertCreated();

        $this->assertSame('120.00', Order::with('items')->firstOrFail()->items->first()->unit_cost);
    }

    // Admin: ingredients

    public function test_receiving_stock_updates_the_weighted_average_cost(): void
    {
        $rice = $this->ingredient('Rice', '100', '0.5000');

        $this->actingAs($this->manager())
            ->post(route('admin.ingredients.receive', $rice), ['quantity' => '100', 'unit_cost' => '1.5', 'note' => 'Weekly delivery'])
            ->assertRedirect();

        $rice->refresh();
        $this->assertEqualsWithDelta(200.0, (float) $rice->stock, 0.001);
        $this->assertEqualsWithDelta(1.0, (float) $rice->unit_cost, 0.0001); // (100 x 0.5 + 100 x 1.5) / 200
        $this->assertDatabaseHas('stock_movements', ['ingredient_id' => $rice->id, 'type' => 'purchase', 'note' => 'Weekly delivery']);
    }

    public function test_receiving_without_a_price_keeps_the_cost(): void
    {
        $rice = $this->ingredient('Rice', '100', '0.5000');

        $this->actingAs($this->manager())->post(route('admin.ingredients.receive', $rice), ['quantity' => '50'])->assertRedirect();

        $this->assertEqualsWithDelta(0.5, (float) $rice->fresh()->unit_cost, 0.0001);
        $this->assertEqualsWithDelta(150.0, $this->stock($rice), 0.001);
    }

    public function test_wastage_removes_stock_but_never_more_than_you_have(): void
    {
        $rice = $this->ingredient('Rice', '100', '0.5000');
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.ingredients.waste', $rice), ['quantity' => '30', 'note' => 'Spoiled'])->assertRedirect();
        $this->assertEqualsWithDelta(70.0, $this->stock($rice), 0.001);
        $this->assertEqualsWithDelta(-30.0, (float) StockMovement::where('type', 'waste')->value('quantity'), 0.001);

        $this->actingAs($manager)->post(route('admin.ingredients.waste', $rice), ['quantity' => '500'])
            ->assertSessionHasErrors('quantity', null, 'waste');
        $this->assertEqualsWithDelta(70.0, $this->stock($rice), 0.001);
    }

    public function test_stock_count_sets_the_stock_and_logs_the_difference(): void
    {
        $rice = $this->ingredient('Rice', '70');
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.ingredients.count', $rice), ['counted' => '65.5'])->assertRedirect();
        $this->assertEqualsWithDelta(65.5, $this->stock($rice), 0.001);
        $this->assertEqualsWithDelta(-4.5, (float) StockMovement::where('type', 'adjustment')->value('quantity'), 0.001);

        $this->actingAs($manager)->post(route('admin.ingredients.count', $rice), ['counted' => '65.5']); // no change
        $this->assertSame(1, StockMovement::where('type', 'adjustment')->count());
    }

    public function test_stock_forms_are_validated(): void
    {
        $rice = $this->ingredient('Rice', '10');
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.ingredients.receive', $rice), ['quantity' => '-5'])->assertSessionHasErrors('quantity', null, 'receive');
        $this->actingAs($manager)->post(route('admin.ingredients.receive', $rice), ['quantity' => 'abc'])->assertSessionHasErrors('quantity', null, 'receive');
        $this->actingAs($manager)->post(route('admin.ingredients.count', $rice), ['counted' => '-1'])->assertSessionHasErrors('counted', null, 'count');
        $this->assertEqualsWithDelta(10.0, $this->stock($rice), 0.001);
    }

    public function test_new_ingredient_with_opening_stock(): void
    {
        $this->actingAs($this->manager())->post(route('admin.ingredients.store'), [
            'name' => 'Salt',
            'unit' => 'kg',
            'reorder_level' => '1',
            'unit_cost' => '40',
            'opening_stock' => '10',
            'is_active' => 1,
        ])->assertRedirect();

        $salt = Ingredient::firstWhere('name', 'Salt');
        $this->assertEqualsWithDelta(10.0, $this->stock($salt), 0.001);
        $this->assertDatabaseHas('stock_movements', ['ingredient_id' => $salt->id, 'type' => 'adjustment', 'note' => 'Opening stock']);
    }

    public function test_ingredient_validation_and_unique_name(): void
    {
        $this->ingredient('Rice', '1');
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.ingredients.store'), ['name' => 'Rice', 'unit' => 'kg', 'reorder_level' => '1', 'unit_cost' => '1', 'is_active' => 1])
            ->assertSessionHasErrors('name');
        $this->actingAs($manager)->post(route('admin.ingredients.store'), ['name' => 'Oil', 'unit' => 'barrels', 'reorder_level' => '-1', 'unit_cost' => '1', 'is_active' => 1])
            ->assertSessionHasErrors(['unit', 'reorder_level']);
    }

    public function test_an_ingredient_used_in_a_recipe_cannot_be_deleted(): void
    {
        $rice = $this->ingredient('Rice', '10');
        $item = $this->recipeItem([[$rice, '300']]);
        $manager = $this->manager();

        $this->actingAs($manager)->delete(route('admin.ingredients.destroy', $rice))->assertSessionHas('error');
        $this->assertNotSoftDeleted($rice);

        RecipeLine::where('menu_item_id', $item->id)->delete();
        $this->actingAs($manager)->delete(route('admin.ingredients.destroy', $rice))->assertRedirect();
        $this->assertSoftDeleted($rice);
    }

    public function test_inventory_pages_are_for_admins_and_managers(): void
    {
        $rice = $this->ingredient('Rice', '10');

        $this->get('/admin/ingredients')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/ingredients')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->post(route('admin.ingredients.receive', $rice), ['quantity' => 5])->assertForbidden();

        $this->actingAs($this->manager())->get('/admin/ingredients')->assertOk()->assertSee('Rice');
        $this->actingAs($this->manager())->get(route('admin.ingredients.show', $rice))->assertOk()->assertSee('Stock history');
    }

    public function test_suppliers_can_be_managed(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.content.store', 'suppliers'), ['name' => 'Fresh Foods', 'phone' => '01711111111', 'is_active' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', ['name' => 'Fresh Foods']);
        $this->assertSame(1, Supplier::count());
    }

    // Admin: menu item form

    public function test_item_form_saves_stock_mode_cost_recipe_and_size_factor(): void
    {
        $rice = $this->ingredient('Rice', '1000');
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->manager())->post(route('admin.items.store'), [
            'menu_category_id' => $category->id,
            'name_en' => 'Kacchi',
            'price' => '400',
            'is_available' => 1,
            'is_featured' => 0,
            'is_special' => 0,
            'stock_mode' => 'recipe',
            'cost_price' => '0',
            'recipe_form' => 1,
            'recipe' => [['ingredient_id' => $rice->id, 'quantity' => '300']],
            'variations' => [['name_en' => 'Half', 'price' => '250', 'portion_factor' => '0.6', 'cost_price' => '90']],
            'addons' => [['name_en' => 'Egg', 'price' => '30', 'cost_price' => '10']],
        ])->assertRedirect();

        $item = MenuItem::firstWhere('slug', 'kacchi');
        $this->assertSame('recipe', $item->stock_mode);
        $this->assertSame(1, $item->recipeLines()->count());
        $this->assertEqualsWithDelta(30.0, $item->recipeCost(), 0.001);
        $this->assertEqualsWithDelta(0.6, (float) $item->variations->first()->portion_factor, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $item->addons->first()->cost_price, 0.001);
    }

    public function test_recipe_can_be_cleared_from_the_form(): void
    {
        $rice = $this->ingredient('Rice', '1000');
        $item = $this->recipeItem([[$rice, '300']]);

        $this->actingAs($this->manager())->put(route('admin.items.update', $item), [
            'menu_category_id' => $item->menu_category_id,
            'name_en' => $item->name_en,
            'slug' => $item->slug,
            'price' => '400',
            'is_available' => 1,
            'is_featured' => 0,
            'is_special' => 0,
            'stock_mode' => 'recipe',
            'recipe_form' => 1,
        ])->assertRedirect();

        $this->assertSame(0, $item->recipeLines()->count());
    }

    public function test_item_form_without_recipe_section_leaves_the_recipe_alone(): void
    {
        $rice = $this->ingredient('Rice', '1000');
        $item = $this->recipeItem([[$rice, '300']]);

        $this->actingAs($this->manager())->put(route('admin.items.update', $item), [
            'menu_category_id' => $item->menu_category_id,
            'name_en' => $item->name_en,
            'slug' => $item->slug,
            'price' => '400',
            'is_available' => 1,
            'is_featured' => 0,
            'is_special' => 0,
        ])->assertRedirect();

        $this->assertSame(1, $item->recipeLines()->count());
        $this->assertSame('recipe', $item->fresh()->stock_mode);
    }

    public function test_item_form_validation_for_stock_and_recipe(): void
    {
        $rice = $this->ingredient('Rice', '1000');
        $category = MenuCategory::factory()->create();
        $base = ['menu_category_id' => $category->id, 'name_en' => 'Dish', 'price' => '100', 'is_available' => 1, 'is_featured' => 0, 'is_special' => 0];
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.items.store'), $base + ['stock_mode' => 'quantity'])->assertSessionHasErrors('stock_qty');
        $this->actingAs($manager)->post(route('admin.items.store'), $base + ['stock_mode' => 'turbo'])->assertSessionHasErrors('stock_mode');
        $this->actingAs($manager)->post(route('admin.items.store'), $base + ['recipe_form' => 1, 'recipe' => [['ingredient_id' => 9999, 'quantity' => '1']]])
            ->assertSessionHasErrors('recipe.0.ingredient_id');
        $this->actingAs($manager)->post(route('admin.items.store'), $base + ['recipe_form' => 1, 'recipe' => [
            ['ingredient_id' => $rice->id, 'quantity' => '1'],
            ['ingredient_id' => $rice->id, 'quantity' => '2'],
        ]])->assertSessionHasErrors();
        $this->actingAs($manager)->post(route('admin.items.store'), $base + ['recipe_form' => 1, 'recipe' => [['ingredient_id' => $rice->id, 'quantity' => '0']]])
            ->assertSessionHasErrors('recipe.0.quantity');

        $this->assertDatabaseCount('menu_items', 0);
    }

    // Alerts

    public function test_low_stock_shows_on_the_dashboard_and_the_menu(): void
    {
        $this->ingredient('Cooking Oil', '1', '1.0000', ['reorder_level' => '5']);
        MenuItem::factory()->create(['name_en' => 'Kacchi Biryani', 'stock_mode' => 'quantity', 'stock_qty' => 2]);

        $this->actingAs($this->manager())->get('/admin')
            ->assertOk()->assertSee('Low stock alerts')->assertSee('Cooking Oil')->assertSee('Kacchi Biryani');

        $this->get('/menu')->assertSee('Only 2 left');
    }

    public function test_staff_do_not_see_low_stock_alerts(): void
    {
        $this->ingredient('Cooking Oil', '1', '1.0000', ['reorder_level' => '5']);

        $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk()->assertDontSee('Low stock alerts');
    }
}
