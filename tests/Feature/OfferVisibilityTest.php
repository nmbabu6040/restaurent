<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OfferVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-15 20:00:00'));
    }

    private function offer(array $attributes = []): Promotion
    {
        return Promotion::create(array_merge(['title_en' => 'Free Borhani', 'is_active' => true, 'sort_order' => 1], $attributes));
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_only_live_offers_are_shown(): void
    {
        $this->offer(['title_en' => 'Always on']);
        $this->offer(['title_en' => 'Future one', 'starts_at' => now()->addDay()]);
        $this->offer(['title_en' => 'Old one', 'ends_at' => now()->subMinute()]);
        $this->offer(['title_en' => 'Switched off', 'is_active' => false]);
        $this->offer(['title_en' => 'In window', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->assertEqualsCanonicalizing(['Always on', 'In window'], Promotion::current()->pluck('title_en')->all());

        $this->get('/')->assertSee('Always on')->assertSee('In window')
            ->assertDontSee('Future one')->assertDontSee('Old one')->assertDontSee('Switched off');
        $this->get('/offers')->assertSee('Always on')->assertDontSee('Old one');
    }

    public function test_an_offer_that_ends_today_stays_live_all_day(): void
    {
        $this->actingAs($this->manager())->post(route('admin.content.store', 'promotions'), [
            'title_en' => 'Weekend deal',
            'starts_at' => '2026-11-15',
            'ends_at' => '2026-11-15',
            'is_active' => 1,
        ])->assertRedirect();

        $offer = Promotion::firstOrFail();
        $this->assertSame('2026-11-15 00:00:00', $offer->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-15 23:59:59', $offer->ends_at->format('Y-m-d H:i:s'));

        $this->get('/')->assertSee('Weekend deal'); // 8 PM on its last day

        $this->travelTo(Carbon::parse('2026-11-16 00:00:01'));
        $this->get('/')->assertDontSee('Weekend deal');
    }

    public function test_dates_are_validated(): void
    {
        $manager = $this->manager();
        $valid = ['title_en' => 'Deal', 'is_active' => 1];

        $this->actingAs($manager)->post(route('admin.content.store', 'promotions'), $valid + ['starts_at' => '2026-11-15', 'ends_at' => '2026-11-10'])
            ->assertSessionHasErrors('ends_at');
        $this->actingAs($manager)->post(route('admin.content.store', 'promotions'), $valid + ['starts_at' => 'tomorrow'])
            ->assertSessionHasErrors('starts_at');
        $this->actingAs($manager)->post(route('admin.content.store', 'promotions'), $valid + ['ends_at' => '2026-11-20'])->assertRedirect(); // no start is fine

        $this->assertSame(1, Promotion::count());
    }

    public function test_editing_keeps_the_dates_and_the_form_shows_plain_dates(): void
    {
        $offer = $this->offer(['starts_at' => '2026-11-10 14:30:00', 'ends_at' => '2026-11-20 09:00:00']);
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('admin.content.edit', ['promotions', $offer->id]))
            ->assertSee('value="2026-11-10"', false)->assertSee('value="2026-11-20"', false);

        $this->actingAs($manager)->put(route('admin.content.update', ['promotions', $offer->id]), [
            'title_en' => 'Free Borhani',
            'starts_at' => '2026-11-10',
            'ends_at' => '2026-11-20',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('2026-11-20 23:59:59', $offer->fresh()->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_the_admin_list_says_why_an_offer_is_not_showing(): void
    {
        $this->offer(['title_en' => 'Live one']);
        $this->offer(['title_en' => 'Old one', 'ends_at' => now()->subDay()]);
        $this->offer(['title_en' => 'Future one', 'starts_at' => now()->addDays(3)]);
        $this->offer(['title_en' => 'Off one', 'is_active' => false]);

        $this->actingAs($this->manager())->get('/admin/content/promotions')
            ->assertOk()->assertSee('Live')->assertSee('Expired on')->assertSee('Scheduled: starts')->assertSee('Hidden');
    }

    public function test_the_home_page_shows_up_to_six_offers(): void
    {
        foreach (range(1, 8) as $i) {
            $this->offer(['title_en' => "Offer number {$i}", 'sort_order' => $i]);
        }

        $html = $this->get('/')->getContent();

        foreach (range(1, 6) as $i) {
            $this->assertStringContainsString("Offer number {$i}", $html);
        }
        $this->assertStringNotContainsString('Offer number 7', $html);
        $this->assertStringNotContainsString('Offer number 8', $html);
    }

    public function test_a_coupon_that_ends_today_works_all_day(): void
    {
        $this->actingAs($this->manager())->post(route('admin.content.store', 'coupons'), [
            'code' => 'TODAY10',
            'type' => 'percent',
            'value' => '10',
            'min_subtotal' => '0',
            'starts_at' => '2026-11-15',
            'ends_at' => '2026-11-15',
            'is_active' => 1,
        ])->assertRedirect();

        $coupon = Coupon::firstOrFail();
        $this->assertSame('2026-11-15 23:59:59', $coupon->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('Live', $coupon->statusLabel());
        $this->assertSame([3800, null], (new CouponService)->evaluate($coupon, 38000));

        $this->travelTo(Carbon::parse('2026-11-16 00:00:01'));
        $this->assertSame([0, 'expired'], (new CouponService)->evaluate($coupon->fresh(), 38000));
        $this->assertSame('Expired', $coupon->fresh()->statusLabel());
    }
}
