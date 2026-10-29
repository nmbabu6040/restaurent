<?php

namespace Tests\Feature;

use App\Models\FoodAddon;
use App\Models\FoodVariation;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->manager = User::factory()->manager()->create();
    }

    private function payload(MenuCategory $category, array $overrides = []): array
    {
        return array_merge([
            'menu_category_id' => $category->id,
            'name_en' => 'Beef Bhuna',
            'name_bn' => 'গরুর ভুনা',
            'price' => '320.00',
            'is_available' => 1,
            'is_featured' => 0,
            'is_special' => 0,
            'dietary' => ['halal'],
            'sort_order' => 1,
        ], $overrides);
    }

    // Categories

    public function test_category_can_be_created_with_an_automatic_slug(): void
    {
        $this->actingAs($this->manager)->post(route('admin.categories.store'), [
            'name_en' => 'Starters',
            'name_bn' => 'স্টার্টার',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('menu_categories', ['name_en' => 'Starters', 'slug' => 'starters']);
    }

    public function test_category_slug_must_be_unique(): void
    {
        MenuCategory::factory()->create(['name_en' => 'Starters', 'slug' => 'starters']);

        $this->actingAs($this->manager)->post(route('admin.categories.store'), [
            'name_en' => 'Starters',
            'is_active' => 1,
        ])->assertSessionHasErrors('slug');
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->create(['menu_category_id' => $category->id]);

        $this->actingAs($this->manager)->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($category);
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->manager)->delete(route('admin.categories.destroy', $category))->assertRedirect();

        $this->assertSoftDeleted($category);
    }

    // Items

    public function test_item_can_be_created(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category))
            ->assertRedirect(route('admin.items.index'));

        $this->assertDatabaseHas('menu_items', ['name_en' => 'Beef Bhuna', 'slug' => 'beef-bhuna', 'price' => '320.00']);
        $this->assertSame(['halal'], MenuItem::firstWhere('slug', 'beef-bhuna')->dietary);
    }

    public function test_item_validation_rules(): void
    {
        $category = MenuCategory::factory()->create();
        $hidden = MenuCategory::factory()->create();
        $hidden->delete();

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category, [
            'price' => '-5',
            'name_en' => '',
        ]))->assertSessionHasErrors(['price', 'name_en']);

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category, ['price' => '12.345']))
            ->assertSessionHasErrors('price');

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category, ['menu_category_id' => 9999]))
            ->assertSessionHasErrors('menu_category_id');

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($hidden))
            ->assertSessionHasErrors('menu_category_id');

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_image_is_stored_and_old_image_is_removed_on_replace(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category) + [
            'image' => UploadedFile::fake()->image('dish.jpg', 800, 600),
        ])->assertRedirect();

        $item = MenuItem::firstWhere('slug', 'beef-bhuna');
        $this->assertStringStartsWith('menu/', $item->image_path);
        Storage::disk('public')->assertExists($item->image_path);

        if (str_ends_with($item->image_path, '.webp')) {
            Storage::disk('public')->assertExists(substr($item->image_path, 0, -5) . '-480.webp');
        }

        $old = $item->image_path;

        $this->actingAs($this->manager)->put(route('admin.items.update', $item), $this->payload($category) + [
            'image' => UploadedFile::fake()->image('new.png', 400, 400),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($item->fresh()->image_path);
    }

    public function test_image_can_be_removed(): void
    {
        $category = MenuCategory::factory()->create();
        Storage::disk('public')->put('menu/old.webp', 'x');
        Storage::disk('public')->put('menu/old-480.webp', 'x');
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'image_path' => 'menu/old.webp']);

        $this->actingAs($this->manager)->put(route('admin.items.update', $item), $this->payload($category) + ['remove_image' => 1])
            ->assertRedirect();

        $this->assertNull($item->fresh()->image_path);
        Storage::disk('public')->assertMissing('menu/old.webp');
        Storage::disk('public')->assertMissing('menu/old-480.webp');
    }

    public function test_only_safe_image_uploads_are_accepted(): void
    {
        $category = MenuCategory::factory()->create();

        $bad = [
            'php script' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'fake jpg with text inside' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo 1; ?>'),
            'pdf' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'too large' => UploadedFile::fake()->image('big.jpg', 100, 100)->size(3000),
        ];

        foreach ($bad as $label => $file) {
            $this->actingAs($this->manager)
                ->post(route('admin.items.store'), $this->payload($category, ['name_en' => "Dish {$label}"]) + ['image' => $file])
                ->assertSessionHasErrors('image');
        }

        $this->assertDatabaseCount('menu_items', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_item_is_soft_deleted(): void
    {
        $item = MenuItem::factory()->create();

        $this->actingAs($this->manager)->delete(route('admin.items.destroy', $item))->assertRedirect();

        $this->assertSoftDeleted($item);
        $this->get('/menu/' . $item->slug)->assertNotFound();
    }

    public function test_availability_can_be_toggled(): void
    {
        $item = MenuItem::factory()->create();

        $this->actingAs($this->manager)
            ->patchJson(route('admin.items.availability', $item), ['is_available' => 0])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertFalse($item->fresh()->is_available);
    }

    public function test_item_list_search_filter_and_sort(): void
    {
        $a = MenuCategory::factory()->create();
        $b = MenuCategory::factory()->create();
        MenuItem::factory()->create(['menu_category_id' => $a->id, 'name_en' => 'Alpha Dish', 'price' => '100.00']);
        MenuItem::factory()->create(['menu_category_id' => $b->id, 'name_en' => 'Beta Dish', 'price' => '200.00']);

        $this->actingAs($this->manager)->get(route('admin.items.index', ['q' => 'Alpha']))
            ->assertSee('Alpha Dish')->assertDontSee('Beta Dish');

        $this->actingAs($this->manager)->get(route('admin.items.index', ['category' => $b->id]))
            ->assertSee('Beta Dish')->assertDontSee('Alpha Dish');

        $this->actingAs($this->manager)->get(route('admin.items.index', ['sort' => 'price', 'dir' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Beta Dish', 'Alpha Dish']);

        // Invalid sort columns are rejected instead of reaching the query.
        $this->actingAs($this->manager)->get(route('admin.items.index', ['sort' => 'password']))
            ->assertSessionHasErrors('sort');
    }

    // Sizes and extras

    public function test_sizes_and_extras_are_saved_and_blank_rows_ignored(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category, [
            'variations' => [
                ['name_en' => 'Half', 'name_bn' => 'হাফ', 'price' => '250'],
                ['name_en' => 'Full', 'price' => '380', 'is_active' => 1],
                ['name_en' => '', 'price' => ''],
            ],
            'addons' => [['name_en' => 'Egg', 'price' => '30']],
        ]))->assertRedirect();

        $item = MenuItem::firstWhere('slug', 'beef-bhuna');
        $this->assertSame(['Half', 'Full'], $item->variations->pluck('name_en')->all());
        $this->assertSame(['Egg'], $item->addons->pluck('name_en')->all());
    }

    public function test_option_validation(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category, [
            'variations' => [['name_en' => 'Half', 'price' => '-1']],
        ]))->assertSessionHasErrors('variations.0.price');

        $this->actingAs($this->manager)->post(route('admin.items.store'), $this->payload($category, [
            'addons' => [['name_en' => '', 'price' => '30']],
        ]))->assertSessionHasErrors('addons.0.name_en');
    }

    public function test_options_are_updated_in_place_and_removed_rows_are_deleted(): void
    {
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'name_en' => 'Beef Bhuna', 'slug' => 'beef-bhuna']);
        $half = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Half', 'price' => '250.00']);
        $full = FoodVariation::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Full', 'price' => '380.00']);
        FoodAddon::factory()->create(['menu_item_id' => $item->id, 'name_en' => 'Egg']);

        $this->actingAs($this->manager)->put(route('admin.items.update', $item), $this->payload($category, [
            'variations' => [
                ['id' => $half->id, 'name_en' => 'Half', 'price' => '260'],
                ['name_en' => 'Family', 'price' => '700'],
            ],
            // addons omitted entirely: the existing one must go
        ]))->assertRedirect();

        $this->assertSame('260.00', $half->fresh()->price);          // same row, new price
        $this->assertModelMissing($full);                            // removed from the form
        $this->assertSame(['Half', 'Family'], $item->variations()->pluck('name_en')->all());
        $this->assertSame(0, $item->addons()->count());
    }
}
