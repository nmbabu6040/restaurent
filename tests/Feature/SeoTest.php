<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_has_title_canonical_open_graph_and_structured_data(): void
    {
        $this->get('/')
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="description"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Restaurant"', false);
    }

    public function test_item_page_has_menu_item_structured_data_with_the_price(): void
    {
        MenuItem::factory()->create(['name_en' => 'Kacchi Biryani', 'slug' => 'kacchi-biryani', 'price' => '380.00']);

        $this->get('/menu/kacchi-biryani')
            ->assertSee('"@type":"MenuItem"', false)
            ->assertSee('"price":"380.00"', false)
            ->assertSee('"priceCurrency":"BDT"', false);
    }

    public function test_personal_pages_are_marked_noindex(): void
    {
        $this->get('/cart')->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->get('/orders/track')->assertSee('noindex', false);
    }

    public function test_robots_txt_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_robots_txt_in_production_hides_private_areas_and_lists_the_sitemap(): void
    {
        $this->app->detectEnvironment(fn() => 'production');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /checkout')
            ->assertSee('Sitemap: ');
    }

    public function test_sitemap_lists_visible_items_only(): void
    {
        MenuItem::factory()->create(['slug' => 'kacchi-biryani']);
        $hidden = MenuCategory::factory()->inactive()->create();
        MenuItem::factory()->create(['menu_category_id' => $hidden->id, 'slug' => 'secret-dish']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('menu.show', 'kacchi-biryani'), false)
            ->assertDontSee('secret-dish', false)
            ->assertSee(route('home'), false);
    }
}
