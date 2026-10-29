<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_load_on_a_clean_database(): void
    {
        foreach (
            [
                '/',
                '/menu',
                '/about',
                '/gallery',
                '/offers',
                '/testimonials',
                '/faq',
                '/contact',
                '/reservations',
                '/privacy-policy',
                '/terms-and-conditions',
                '/orders/track',
                '/cart',
            ] as $url
        ) {
            $this->get($url)->assertOk();
        }
    }

    public function test_unknown_url_shows_the_custom_404_page(): void
    {
        $this->get('/there-is-no-such-page')->assertNotFound()->assertSee('Page not found');
    }

    public function test_item_page_and_hidden_category(): void
    {
        MenuItem::factory()->create(['name_en' => 'Kacchi Biryani', 'slug' => 'kacchi-biryani']);
        $hidden = MenuCategory::factory()->inactive()->create();
        MenuItem::factory()->create(['menu_category_id' => $hidden->id, 'slug' => 'secret-dish']);

        $this->get('/menu/kacchi-biryani')->assertOk()->assertSee('Kacchi Biryani');
        $this->get('/menu/secret-dish')->assertNotFound();
        $this->get('/menu/does-not-exist')->assertNotFound();
    }

    public function test_menu_search_and_category_filter(): void
    {
        $mains = MenuCategory::factory()->create(['slug' => 'mains']);
        $sweets = MenuCategory::factory()->create(['slug' => 'sweets']);
        MenuItem::factory()->create(['menu_category_id' => $mains->id, 'name_en' => 'Kacchi Biryani']);
        MenuItem::factory()->create(['menu_category_id' => $sweets->id, 'name_en' => 'Mishti Doi']);

        $this->get('/menu?q=biryani')->assertSee('Kacchi Biryani')->assertDontSee('Mishti Doi');
        $this->get('/menu?category=sweets')->assertSee('Mishti Doi')->assertDontSee('Kacchi Biryani');
        $this->get('/menu?q=zzzz')->assertSee('No dishes found');
    }

    public function test_ajax_menu_request_returns_only_the_grid(): void
    {
        MenuItem::factory()->create(['name_en' => 'Kacchi Biryani']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/menu')
            ->assertOk()
            ->assertSee('Kacchi Biryani')
            ->assertDontSee('<html', false);
    }

    public function test_sold_out_items_are_labelled_and_cannot_be_added(): void
    {
        MenuItem::factory()->unavailable()->create(['name_en' => 'Firni']);

        $this->get('/menu')->assertSee('Sold out')->assertDontSee('data-cart-add', false);
    }

    public function test_menu_text_is_escaped(): void
    {
        MenuItem::factory()->create(['name_en' => '<script>alert(1)</script>']);

        $this->get('/menu')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_language_switch_is_remembered(): void
    {
        $this->get('/lang/bn')->assertRedirect();
        $this->get('/')->assertSee('lang="bn"', false)->assertSee('মেনু');

        $this->get('/lang/fr')->assertNotFound();
    }
}
