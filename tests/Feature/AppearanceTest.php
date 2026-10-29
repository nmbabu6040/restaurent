<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function save(array $overrides = [])
    {
        $payload = array_merge(['whatsapp_enabled' => 0, 'back_to_top_enabled' => 1, 'maintenance_enabled' => 0], $overrides);

        return $this->actingAs(User::factory()->admin()->create())->put(route('admin.appearance.update'), $payload);
    }

    public function test_only_admins_can_open_the_page(): void
    {
        $this->get('/admin/appearance')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->manager()->create())->get('/admin/appearance')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/appearance')->assertOk()->assertSee('Maintenance mode');
    }

    public function test_section_color_is_saved_applied_and_can_be_reset(): void
    {
        $this->save(['color_hero' => '#0F3D2E'])->assertRedirect();
        $this->get('/')->assertSee('--sec-bg: #0F3D2E', false);

        $this->save(['color_hero' => '#0F3D2E', 'reset_hero' => 1])->assertRedirect();
        $this->get('/')->assertDontSee('#0F3D2E', false);
    }

    public function test_unreadable_or_invalid_colors_are_rejected(): void
    {
        $this->save(['color_hero' => '#808080'])->assertSessionHasErrors('color_hero');
        $this->save(['color_hero' => 'red; background:url(x)'])->assertSessionHasErrors('color_hero');
        $this->save(['color_footer' => '#FFF'])->assertSessionHasErrors('color_footer');
    }

    public function test_the_default_color_is_not_stored_as_a_custom_one(): void
    {
        $this->save(['color_hero' => '#FAF7F2'])->assertRedirect();

        $this->assertNull(setting('color_hero'));
    }

    public function test_whatsapp_button_uses_a_normalised_number(): void
    {
        $this->get('/')->assertDontSee('wa.me/', false);

        $this->save(['whatsapp_enabled' => 1, 'whatsapp_number' => '01712-345678', 'whatsapp_message_en' => 'Hello there'])->assertRedirect();

        $this->get('/')->assertSee('https://wa.me/8801712345678?text=Hello%20there', false);
    }

    public function test_back_to_top_button_can_be_switched_off(): void
    {
        $this->get('/')->assertSee('data-back-to-top-wrap', false);

        $this->save(['back_to_top_enabled' => 0])->assertRedirect();

        $this->get('/')->assertDontSee('data-back-to-top-wrap', false);
    }

    public function test_logo_favicon_and_author_are_used_on_the_site(): void
    {
        $this->save([
            'logo_header' => UploadedFile::fake()->image('logo.png', 400, 120),
            'favicon' => UploadedFile::fake()->image('icon.png', 300, 300),
            'author_name' => 'Studio Example',
            'author_url' => 'https://studio.example',
        ])->assertRedirect();

        $logo = setting('logo_header');
        $icon = setting('favicon');
        Storage::disk('public')->assertExists($logo);
        Storage::disk('public')->assertExists($icon);

        $this->get('/')
            ->assertSee(Storage::disk('public')->url($logo), false)
            ->assertSee('<link rel="icon" href="' . Storage::disk('public')->url($icon) . '">', false)
            ->assertSee('<meta name="author" content="Studio Example">', false)
            ->assertSee('Studio Example');
    }

    public function test_replaced_and_removed_images_are_deleted(): void
    {
        $this->save(['logo_header' => UploadedFile::fake()->image('a.png', 200, 80)]);
        $first = setting('logo_header');

        $this->save(['logo_header' => UploadedFile::fake()->image('b.png', 200, 80)]);
        Storage::disk('public')->assertMissing($first);

        $second = setting('logo_header');
        $this->save(['remove_logo_header' => 1]);
        Storage::disk('public')->assertMissing($second);
        $this->assertNull(setting('logo_header'));
    }

    public function test_unsafe_uploads_are_rejected(): void
    {
        $this->save(['logo_header' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php')])->assertSessionHasErrors('logo_header');
        $this->save(['favicon' => UploadedFile::fake()->createWithContent('i.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('favicon');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_owner_profile_shows_on_the_about_page(): void
    {
        $this->save(['owner_name' => 'Chef Meher', 'owner_title_en' => 'Founder', 'owner_bio_en' => 'Cooking since 1998.'])->assertRedirect();

        $this->get('/about')->assertSee('Chef Meher')->assertSee('Founder')->assertSee('Cooking since 1998.');
    }

    public function test_food_pages_have_share_links(): void
    {
        MenuItem::factory()->create(['slug' => 'kacchi-biryani', 'name_en' => 'Kacchi Biryani']);

        $this->get('/menu/kacchi-biryani')
            ->assertSee('facebook.com/sharer', false)
            ->assertSee('wa.me/?text=', false)
            ->assertSee('data-copy-link', false);
    }

    // Maintenance mode

    private function maintenanceOn(): void
    {
        RestaurantSetting::create(['key' => 'maintenance_enabled', 'value_en' => '1']);
    }

    public function test_visitors_see_the_maintenance_page_with_a_503(): void
    {
        $this->maintenanceOn();

        $this->get('/')->assertStatus(503)->assertHeader('Retry-After')->assertSee('We will be back soon');
        $this->get('/menu')->assertStatus(503);
    }

    public function test_the_maintenance_message_can_be_customised(): void
    {
        $this->save(['maintenance_enabled' => 1, 'maintenance_message_en' => 'Back at 6 PM.'])->assertRedirect();

        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('Back at 6 PM.');
    }

    public function test_login_staff_and_payment_confirmations_still_work_in_maintenance(): void
    {
        $this->maintenanceOn();

        $this->get('/admin/login')->assertOk();
        $this->post('/payments/sslcommerz/ipn', [])->assertOk();
        $this->actingAs(User::factory()->staff()->create())->get('/')->assertOk();
    }

    public function test_admin_sees_a_maintenance_banner(): void
    {
        $this->maintenanceOn();

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertSee('Maintenance mode is ON');
    }

    public function test_site_returns_to_normal_when_switched_off(): void
    {
        $this->save(['maintenance_enabled' => 1])->assertRedirect();
        $this->save(['maintenance_enabled' => 0])->assertRedirect();

        auth()->logout();
        $this->get('/')->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'maintenance.on']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'maintenance.off']);
    }

    // Admin shell

    public function test_dark_theme_cookie_is_applied_server_side(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertSee('data-theme="light"', false);
        $this->actingAs($admin)->withUnencryptedCookie('admin_theme', 'dark')->get('/admin')->assertSee('data-theme="dark"', false);
        $this->actingAs($admin)->withUnencryptedCookie('admin_theme', 'x<script>')->get('/admin')->assertSee('data-theme="light"', false);
    }
}
