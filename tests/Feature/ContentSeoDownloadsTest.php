<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\GalleryImage;
use App\Models\HomeSection;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Page;
use App\Models\RestaurantSetting;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentSeoDownloadsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge(['slug' => 'our-story', 'title_en' => 'Our Story', 'body_en' => 'Hello there', 'is_active' => true], $attributes));
    }

    // Pages

    public function test_a_manager_creates_a_page_and_markdown_is_made_safe(): void
    {
        $this->actingAs($this->manager())->post(route('admin.pages.store'), [
            'title_en' => 'Our Story',
            'body_en' => "## Hello\n\n**bold** text\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
            'show_in_header' => 1,
            'show_in_footer' => 0,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('our-story', Page::firstOrFail()->slug);

        $html = $this->get('/page/our-story')->assertOk()->assertSee('Our Story')->getContent();
        $this->assertStringContainsString('<h2>Hello</h2>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_hidden_and_unknown_pages_are_404(): void
    {
        $this->page(['slug' => 'secret', 'is_active' => false]);

        $this->get('/page/secret')->assertNotFound();
        $this->get('/page/nope')->assertNotFound();
    }

    public function test_pages_appear_in_the_menu_when_asked(): void
    {
        $this->page(['title_en' => 'Catering', 'slug' => 'catering', 'show_in_header' => true, 'show_in_footer' => true]);
        $this->page(['title_en' => 'Secret page', 'slug' => 'secret', 'is_active' => false, 'show_in_header' => true]);

        $this->get('/')->assertSee(route('pages.show', 'catering'), false)->assertDontSee('Secret page');
    }

    public function test_page_validation_and_the_bangla_text(): void
    {
        $manager = $this->manager();
        $this->page();

        $this->actingAs($manager)->post(route('admin.pages.store'), ['title_en' => '', 'show_in_header' => 0, 'show_in_footer' => 0, 'is_active' => 1])
            ->assertSessionHasErrors('title_en');
        $this->actingAs($manager)->post(route('admin.pages.store'), ['title_en' => 'Our Story', 'show_in_header' => 0, 'show_in_footer' => 0, 'is_active' => 1])
            ->assertSessionHasErrors('slug'); // already taken

        Page::where('slug', 'our-story')->update(['title_bn' => 'আমাদের গল্প', 'body_bn' => 'বাংলা লেখা']);
        $this->get('/lang/bn');
        $this->get('/page/our-story')->assertSee('বাংলা লেখা');
    }

    public function test_privacy_and_terms_are_editable_and_protected(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy'); // built-in text before the seeder

        $this->seed(PageSeeder::class);
        $privacy = Page::where('slug', 'privacy-policy')->firstOrFail();
        $manager = $this->manager();

        $this->actingAs($manager)->put(route('admin.pages.update', $privacy), [
            'title_en' => 'Privacy Policy',
            'slug' => 'hacked',
            'body_en' => 'We keep your data safe.',
            'show_in_header' => 0,
            'show_in_footer' => 0,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('privacy-policy', $privacy->fresh()->slug); // the address never changes
        $this->get('/privacy-policy')->assertSee('We keep your data safe.');

        $this->actingAs($manager)->delete(route('admin.pages.destroy', $privacy))->assertSessionHas('error');
        $this->assertModelExists($privacy);
        $this->get('/terms-and-conditions')->assertOk();
    }

    public function test_pages_are_in_the_sitemap_and_staff_cannot_manage_them(): void
    {
        $this->page(['slug' => 'catering', 'title_en' => 'Catering']);

        $this->get('/sitemap.xml')->assertSee(route('pages.show', 'catering'), false);
        $this->actingAs(User::factory()->staff()->create())->get('/admin/pages')->assertForbidden();
    }

    // Home sections

    public function test_the_home_page_follows_the_section_order_and_visibility(): void
    {
        $category = MenuCategory::factory()->create(['name_en' => 'Starters']);
        MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $manager = $this->manager();

        $this->get('/')->assertSee('Explore by Category');

        $this->actingAs($manager)->post(route('admin.home-sections.toggle', HomeSection::firstWhere('key', 'categories')))->assertRedirect();
        $this->get('/')->assertDontSee('Explore by Category');

        $categories = HomeSection::firstWhere('key', 'categories');
        $this->actingAs($manager)->post(route('admin.home-sections.move', [$categories, 'up']))->assertRedirect();
        $this->assertSame('categories', HomeSection::ordered()->first()->key);
        $this->actingAs($manager)->post(route('admin.home-sections.move', [$categories, 'down']))->assertRedirect();
        $this->assertSame('hero', HomeSection::ordered()->first()->key);
    }

    public function test_built_in_headings_can_be_changed(): void
    {
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $section = HomeSection::firstWhere('key', 'categories');

        $this->actingAs($this->manager())->put(route('admin.home-sections.update', $section), [
            'title_en' => 'What are you hungry for?',
            'subtitle_en' => 'Pick a menu',
            'is_visible' => 1,
        ])->assertRedirect();

        $this->get('/')->assertSee('What are you hungry for?')->assertSee('Pick a menu')->assertDontSee('Explore by Category');
    }

    public function test_custom_sections_can_be_created_shown_and_deleted(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('admin.home-sections.store'), [
            'title_en' => 'Catering for events',
            'body_en' => "We cook for **big** groups.\n\n<script>alert(1)</script>",
            'button_text_en' => 'Ask us',
            'button_url' => '/contact',
            'bg_color' => '#0F3D2E',
            'is_visible' => 1,
        ])->assertRedirect();

        $section = HomeSection::where('is_custom', true)->firstOrFail();
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Catering for events', $html);
        $this->assertStringContainsString('<strong>big</strong>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('--sec-bg: #0F3D2E', $html);

        $this->actingAs($manager)->delete(route('admin.home-sections.destroy', $section))->assertRedirect();
        $this->assertModelMissing($section);

        $this->actingAs($manager)->delete(route('admin.home-sections.destroy', HomeSection::firstWhere('key', 'hero')))->assertSessionHas('error');
    }

    public function test_custom_section_validation(): void
    {
        $manager = $this->manager();
        $valid = ['title_en' => 'Deal', 'is_visible' => 1];

        $this->actingAs($manager)->post(route('admin.home-sections.store'), $valid + ['button_url' => 'javascript:alert(1)'])->assertSessionHasErrors('button_url');
        $this->actingAs($manager)->post(route('admin.home-sections.store'), $valid + ['button_url' => '//evil.example'])->assertSessionHasErrors('button_url');
        $this->actingAs($manager)->post(route('admin.home-sections.store'), $valid + ['bg_color' => '#808080'])->assertSessionHasErrors('bg_color');
        $this->actingAs($manager)->post(route('admin.home-sections.store'), ['is_visible' => 1])->assertSessionHasErrors('title_en');
        $this->assertSame(0, HomeSection::where('is_custom', true)->count());
    }

    public function test_highlights_and_about_values_are_managed_in_the_admin(): void
    {
        $manager = $this->manager();

        $this->get('/')->assertSee('Fresh every day');
        $this->actingAs($manager)->post(route('admin.content.store', 'usps'), ['title_en' => 'Open late', 'text_en' => 'Until midnight', 'is_active' => 1])->assertRedirect();
        $this->assertSame('usp', Feature::firstWhere('title_en', 'Open late')->group);
        $this->get('/')->assertSee('Open late');

        $this->actingAs($manager)->post(route('admin.content.store', 'values'), ['title_en' => 'Community', 'text_en' => 'We give back', 'is_active' => 1])->assertRedirect();
        $this->assertSame('value', Feature::firstWhere('title_en', 'Community')->group);
        $this->get('/about')->assertSee('Community')->assertDontSee('Open late');

        // A highlight cannot be opened or deleted through the "values" list.
        $usp = Feature::firstWhere('title_en', 'Open late');
        $this->actingAs($manager)->get(route('admin.content.edit', ['values', $usp->id]))->assertNotFound();
        $this->actingAs($manager)->delete(route('admin.content.destroy', ['values', $usp->id]))->assertNotFound();
    }

    public function test_about_mission_and_vision_come_from_settings(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'name_en' => 'Meher',
            'phone_en' => '01712345678',
            'email_en' => 'a@example.com',
            'about_mission_en' => 'Feed the neighbourhood well.',
            'about_vision_en' => 'The best table in town.',
        ])->assertRedirect();

        $this->get('/about')->assertSee('Feed the neighbourhood well.')->assertSee('The best table in town.');
    }

    // Hero image and categories

    public function test_the_hero_can_use_a_photo_with_an_overlay(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $payload = ['whatsapp_enabled' => 0, 'back_to_top_enabled' => 1, 'maintenance_enabled' => 0];

        $this->get('/');

        $this->actingAs($admin)->put(route('admin.appearance.update'), $payload + [
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
            'hero_overlay_color' => '#000000',
            'hero_overlay_opacity' => 40,
            'hero_text_tone' => 'light',
            'hero_height' => 'tall',
        ])->assertRedirect();

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('background-color: #000000; opacity: 0.4;', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('py-28 sm:py-44', $html);
        Storage::disk('public')->assertExists(setting('hero_image'));

        $this->actingAs($admin)->put(route('admin.appearance.update'), $payload + ['remove_hero_image' => 1])->assertRedirect();
        $this->assertNull(setting('hero_image'));
    }

    public function test_hero_settings_are_validated(): void
    {
        $payload = ['whatsapp_enabled' => 0, 'back_to_top_enabled' => 1, 'maintenance_enabled' => 0];

        $this->actingAs($this->admin())->put(route('admin.appearance.update'), $payload + ['hero_overlay_opacity' => 95])->assertSessionHasErrors('hero_overlay_opacity');
        $this->actingAs($this->admin())->put(route('admin.appearance.update'), $payload + ['hero_overlay_color' => 'red'])->assertSessionHasErrors('hero_overlay_color');
        $this->actingAs($this->admin())->put(route('admin.appearance.update'), $payload + ['hero_height' => 'huge'])->assertSessionHasErrors('hero_height');
    }

    public function test_category_boxes_are_colourful_and_show_dish_counts(): void
    {
        $category = MenuCategory::factory()->create(['name_en' => 'Starters', 'slug' => 'starters']);
        MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id]);
        MenuItem::factory()->unavailable()->create(['menu_category_id' => $category->id]);

        $this->get('/')->assertSee('bg-linear-to-br', false)->assertSee('2 dishes')->assertSee(route('menu.index', ['category' => 'starters']), false);
    }

    public function test_the_floating_buttons_sit_on_the_right(): void
    {
        RestaurantSetting::create(['key' => 'whatsapp_enabled', 'value_en' => '1']);
        RestaurantSetting::create(['key' => 'whatsapp_number', 'value_en' => '8801712345678']);

        $this->get('/')->assertSee('fixed bottom-4 right-4', false)->assertDontSee('fixed bottom-4 left-4', false);
    }

    // SEO

    private function seo(array $overrides = [])
    {
        return $this->actingAs($this->admin())->put(route('admin.seo.update'), array_merge(['seo_noindex_all' => 0], $overrides));
    }

    public function test_page_seo_overrides_the_title_and_description(): void
    {
        $this->seo(['seo_home_title_en' => 'Best Biryani in Dhaka | Meher', 'seo_home_description_en' => 'Fresh kacchi every day.'])->assertRedirect();

        $this->get('/')
            ->assertSee('<title>Best Biryani in Dhaka | Meher</title>', false)
            ->assertSee('<meta name="description" content="Fresh kacchi every day.">', false)
            ->assertSee('<meta property="og:title" content="Best Biryani in Dhaka | Meher">', false);

        $this->get('/menu')->assertDontSee('Best Biryani in Dhaka'); // other pages are untouched
    }

    public function test_default_description_image_and_verification_codes(): void
    {
        Storage::fake('public');

        $this->seo([
            'seo_default_description_en' => 'Bangladeshi food, made with care.',
            'seo_google_verification' => 'abcDEF1234567890xyz',
            'seo_bing_verification' => 'BING1234567890ABCDEF',
            'seo_default_image' => UploadedFile::fake()->image('share.png', 1600, 900),
        ])->assertRedirect();

        $path = setting('seo_default_image');
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);

        $this->get('/faq')
            ->assertSee('Bangladeshi food, made with care.')
            ->assertSee('<meta name="google-site-verification" content="abcDEF1234567890xyz">', false)
            ->assertSee('<meta name="msvalidate.01" content="BING1234567890ABCDEF">', false)
            ->assertSee('property="og:image"', false);
    }

    public function test_seo_validation(): void
    {
        $this->seo(['seo_google_verification' => '"><script>alert(1)</script>'])->assertSessionHasErrors('seo_google_verification');
        $this->seo(['seo_home_title_en' => str_repeat('x', 161)])->assertSessionHasErrors('seo_home_title_en');
        $this->seo(['seo_default_image' => UploadedFile::fake()->create('x.php', 5, 'application/x-php')])->assertSessionHasErrors('seo_default_image');
        $this->actingAs($this->manager())->put(route('admin.seo.update'), ['seo_noindex_all' => 0])->assertForbidden();
    }

    public function test_hiding_the_whole_site_from_search_engines(): void
    {
        $this->seo(['seo_noindex_all' => 1])->assertRedirect();

        $this->get('/')->assertSee('<meta name="robots" content="noindex,nofollow">', false)->assertDontSee('rel="canonical"', false);

        $this->app->detectEnvironment(fn() => 'production');
        $this->get('/robots.txt')->assertSee('Disallow: /');

        $this->seo(['seo_noindex_all' => 0])->assertRedirect();
        $this->get('/robots.txt')->assertSee('Disallow: /admin')->assertDontSee('Disallow: /' . "\n");
    }

    public function test_dishes_have_their_own_seo_text_and_the_faq_has_structured_data(): void
    {
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->create(['menu_category_id' => $category->id, 'slug' => 'kacchi', 'name_en' => 'Kacchi', 'seo_title_en' => 'Kacchi Biryani Dhaka', 'seo_description_en' => 'Slow-cooked mutton.']);
        \App\Models\Faq::create(['question_en' => 'Do you deliver?', 'answer_en' => 'Yes, in the city.', 'is_active' => true]);

        $this->get('/menu/kacchi')->assertSee('<title>Kacchi Biryani Dhaka | ', false)->assertSee('Slow-cooked mutton.');
        $this->get('/faq')->assertSee('"@type":"FAQPage"', false)->assertSee('Do you deliver?');
    }

    // Downloads

    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->create($attributes);
        $order->items()->create(['name_en' => 'Kacchi Biryani', 'variation_en' => 'Full', 'quantity' => 2, 'unit_price' => '380.00', 'line_total' => '760.00']);

        return $order;
    }

    public function test_orders_download_as_a_safe_csv_with_date_limits(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $recent = $this->order(['created_at' => '2026-11-14 10:00:00', 'name' => '=HYPERLINK("http://evil.example")']);
        $old = $this->order(['created_at' => '2026-10-01 10:00:00']);
        $manager = $this->manager();

        $csv = $this->actingAs($manager)->get('/admin/downloads/orders?format=csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFReference,Date,Type", $csv);
        $this->assertStringContainsString($recent->reference, $csv);
        $this->assertStringContainsString($old->reference, $csv);
        $this->assertStringContainsString("'=HYPERLINK(", $csv);

        $limited = $this->actingAs($manager)->get('/admin/downloads/orders?format=csv&from=2026-11-01&to=2026-11-30')->streamedContent();
        $this->assertStringContainsString($recent->reference, $limited);
        $this->assertStringNotContainsString($old->reference, $limited);

        $this->assertDatabaseHas('activity_logs', ['action' => 'export.orders', 'user_id' => $manager->id]);
    }

    public function test_every_dataset_downloads_in_both_formats(): void
    {
        $this->order();
        $manager = $this->manager();

        foreach (array_keys(\App\Support\Exports::all()) as $dataset) {
            $csv = $this->actingAs($manager)->get("/admin/downloads/{$dataset}?format=csv")->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent(), $dataset);

            $pdf = $this->actingAs($manager)->get("/admin/downloads/{$dataset}?format=pdf")->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), $dataset);
        }
    }

    public function test_download_rules(): void
    {
        $manager = $this->manager();

        $this->get('/admin/downloads')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/downloads')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/admin/downloads/orders?format=csv')->assertForbidden();

        $this->actingAs($manager)->get('/admin/downloads')->assertOk()->assertSee('Orders')->assertSee('Rider deliveries');
        $this->actingAs($manager)->get('/admin/downloads/nothing?format=csv')->assertNotFound();
        $this->actingAs($manager)->get('/admin/downloads/orders')->assertSessionHasErrors('format');
        $this->actingAs($manager)->get('/admin/downloads/orders?format=xml')->assertSessionHasErrors('format');
        $this->actingAs($manager)->get('/admin/downloads/orders?format=csv&from=2026-11-10&to=2026-11-01')->assertSessionHasErrors('to');
    }

    // Bill and challan

    public function test_the_bill_and_challan_pdfs_download(): void
    {
        $order = $this->order(['status' => 'confirmed']);
        $staff = User::factory()->staff()->create();

        $invoice = $this->actingAs($staff)->get(route('admin.orders.invoice', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $invoice->getContent());
        $this->assertStringContainsString('invoice-' . $order->reference . '.pdf', $invoice->headers->get('Content-Disposition'));

        $challan = $this->actingAs($staff)->get(route('admin.orders.challan', $order))->assertOk();
        $this->assertStringStartsWith('%PDF', $challan->getContent());

        $this->get(route('admin.orders.invoice', $order))->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->rider()->create())->get(route('admin.orders.invoice', $order))->assertForbidden();
    }

    public function test_the_bill_and_challan_show_the_right_details(): void
    {
        RestaurantSetting::create(['key' => 'invoice_bin', 'value_en' => 'BIN-12345']);
        $order = $this->order(['zone_en' => 'Gulshan', 'delivery_fee' => '60.00', 'total' => '820.00', 'subtotal' => '760.00', 'notes' => 'No chili']);
        $order->load('items');

        $this->view('pdf.invoice', ['order' => $order])
            ->assertSee('INVOICE')->assertSee($order->reference)->assertSee('Kacchi Biryani')->assertSee('Full')
            ->assertSee('Tk 760.00')->assertSee('Tk 820.00')->assertSee('BIN: BIN-12345')->assertSee('Area: Gulshan')->assertSee('Due');

        $this->view('pdf.challan', ['order' => $order])
            ->assertSee('DELIVERY CHALLAN')->assertSee('CH-' . $order->reference)->assertSee('Total portions: 2')
            ->assertSee('Received by (customer)')->assertSee('No chili')->assertDontSee('Tk 760.00');
    }

    public function test_a_customer_can_download_their_own_bill_with_the_private_link(): void
    {
        $order = $this->order();

        $this->get(route('orders.show', $order->token))->assertSee(route('orders.invoice', $order->token), false);
        $response = $this->get(route('orders.invoice', $order->token))->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->get('/orders/' . $order->reference . '/invoice')->assertNotFound();
        $this->get('/orders/' . str_repeat('a', 40) . '/invoice')->assertNotFound();

        $order->update(['status' => 'cancelled']);
        $this->get(route('orders.invoice', $order->token))->assertNotFound();
        $this->get(route('orders.show', $order->token))->assertDontSee(route('orders.invoice', $order->token), false);
    }

    // Sidebar

    public function test_the_sidebar_has_icons_groups_search_and_the_new_pages(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        foreach (['data-admin-nav', 'data-nav-filter', 'data-nav-group="operations"', 'Home sections', 'Downloads', '>SEO<', 'Pages'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertGreaterThan(15, substr_count($html, '<svg'));

        $staffHtml = $this->actingAs(User::factory()->staff()->create())->get('/admin')->getContent();
        $this->assertStringNotContainsString('Backups', $staffHtml);
        $this->assertStringNotContainsString('Downloads', $staffHtml);
    }

    public function test_gallery_still_works_after_the_content_system_change(): void
    {
        $this->actingAs($this->manager())->get('/admin/content/gallery')->assertOk();
        $this->assertSame(0, GalleryImage::count());
    }
}
