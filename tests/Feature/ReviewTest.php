<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = MenuItem::factory()->create(['name_en' => 'Kacchi Biryani', 'slug' => 'kacchi-biryani']);
    }

    private function submit(array $overrides = [], ?MenuItem $item = null)
    {
        return $this->postJson(route('reviews.store', ($item ?? $this->item)->slug), array_merge([
            'name' => 'Rahim',
            'rating' => 5,
            'comment' => 'Really tasty biryani',
        ], $overrides));
    }

    private function review(array $attributes = []): Review
    {
        return Review::create(array_merge([
            'menu_item_id' => $this->item->id,
            'name' => 'Rahim',
            'rating' => 5,
            'comment' => 'Tasty',
            'status' => 'approved',
            'is_verified' => false,
            'locale' => 'en',
        ], $attributes));
    }

    private function reply(Review $review, array $attributes = []): ReviewReply
    {
        return $review->replies()->create(array_merge(['name' => 'Karim', 'body' => 'I agree', 'status' => 'approved', 'is_staff' => false], $attributes));
    }

    private function completedOrder(?MenuItem $item = null): Order
    {
        $order = Order::factory()->create(['status' => 'completed', 'name' => 'Rahim Uddin']);
        $order->items()->create([
            'menu_item_id' => ($item ?? $this->item)->id,
            'name_en' => 'Kacchi Biryani',
            'quantity' => 1,
            'unit_price' => '380.00',
            'line_total' => '380.00',
        ]);

        return $order;
    }

    private function page(): string
    {
        return $this->get(route('menu.show', $this->item->slug))->assertOk()->getContent();
    }

    private function staff(): User
    {
        return User::factory()->staff()->create();
    }

    // Posting a review

    public function test_an_anonymous_review_waits_for_approval(): void
    {
        $this->submit()->assertCreated()->assertJsonStructure(['message']);

        $review = Review::firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertFalse($review->is_verified);
        $this->assertNotNull($review->ip_address);
        $this->assertSame(0, $this->item->fresh()->reviews_count);
        $this->assertStringNotContainsString('Really tasty biryani', $this->page());
    }

    public function test_approving_publishes_it_and_updates_the_rating(): void
    {
        $this->submit(['rating' => 4])->assertCreated();
        $review = Review::firstOrFail();

        $this->actingAs($this->staff())->patch(route('admin.reviews.status', $review), ['status' => 'approved'])->assertSessionHas('status');

        $item = $this->item->fresh();
        $this->assertSame(1, $item->reviews_count);
        $this->assertSame('4.00', $item->rating_avg);
        $this->assertStringContainsString('Really tasty biryani', $this->page());
        $this->get('/menu')->assertSee('4.0 (1)');
        $this->assertDatabaseHas('activity_logs', ['action' => 'review.approved']);
    }

    public function test_hiding_removes_it_again(): void
    {
        $review = $this->review();
        app(ReviewService::class)->refresh($this->item->id);
        $this->assertSame(1, $this->item->fresh()->reviews_count);

        $this->actingAs($this->staff())->patch(route('admin.reviews.status', $review), ['status' => 'hidden']);

        $this->assertSame(0, $this->item->fresh()->reviews_count);
        $this->assertStringNotContainsString('Tasty', $this->page());
    }

    public function test_the_average_only_counts_approved_reviews(): void
    {
        $this->review(['rating' => 5]);
        $this->review(['rating' => 3]);
        $this->review(['rating' => 1, 'status' => 'pending']);
        $this->review(['rating' => 1, 'status' => 'hidden']);

        app(ReviewService::class)->refresh($this->item->id);

        $item = $this->item->fresh();
        $this->assertSame(2, $item->reviews_count);
        $this->assertSame('4.00', $item->rating_avg);
        $this->assertStringContainsString('2 reviews', $this->page());
    }

    public function test_validation(): void
    {
        $this->submit(['rating' => 0])->assertStatus(422)->assertJsonValidationErrors('rating');
        $this->submit(['rating' => 6])->assertJsonValidationErrors('rating');
        $this->submit(['rating' => null])->assertJsonValidationErrors('rating');
        $this->submit(['name' => ''])->assertJsonValidationErrors('name');
        $this->submit(['comment' => str_repeat('x', 1001)])->assertJsonValidationErrors('comment');
        $this->submit(['comment' => 'Buy cheap pills at www.spam.example'])->assertJsonValidationErrors('comment');
        $this->submit(['comment' => 'Visit https://spam.example now'])->assertJsonValidationErrors('comment');
        $this->submit(['comment' => 'best deals on cheap-watches.com today'])->assertJsonValidationErrors('comment');
        $this->submit(['website' => 'spam'])->assertJsonValidationErrors('website');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_rating_alone_is_enough(): void
    {
        $this->submit(['comment' => null])->assertCreated();

        $this->assertNull(Review::firstOrFail()->comment);
    }

    public function test_the_same_text_cannot_be_posted_twice_in_a_day(): void
    {
        $this->submit()->assertCreated();
        $this->submit(['name' => 'Someone else'])->assertStatus(422)->assertJsonValidationErrors('comment');

        $this->assertSame(1, Review::count());
    }

    public function test_review_posting_is_rate_limited(): void
    {
        foreach (['one', 'two', 'three'] as $text) {
            $this->submit(['comment' => $text])->assertCreated();
        }

        $this->submit(['comment' => 'four'])->assertStatus(429);
    }

    public function test_hidden_categories_and_unknown_dishes_cannot_be_reviewed(): void
    {
        $hidden = MenuItem::factory()->create(['menu_category_id' => MenuCategory::factory()->inactive()->create()->id]);

        $this->submit([], $hidden)->assertNotFound();
        $this->postJson('/menu/no-such-dish/reviews', ['name' => 'A', 'rating' => 5])->assertNotFound();
    }

    public function test_review_text_is_escaped(): void
    {
        $this->review(['name' => '<b>Mallory</b>', 'comment' => '<script>alert(1)</script>']);

        $html = $this->page();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Mallory&lt;/b&gt;', $html);
    }

    // Verified buyers

    public function test_a_verified_buyer_review_goes_live_at_once(): void
    {
        $order = $this->completedOrder();

        $this->submit(['order_token' => $order->token])->assertCreated()->assertJsonStructure(['redirect']);

        $review = Review::firstOrFail();
        $this->assertTrue($review->is_verified);
        $this->assertSame('approved', $review->status);
        $this->assertSame($order->id, $review->order_id);
        $this->assertSame(1, $this->item->fresh()->reviews_count);
        $this->assertStringContainsString('Verified buyer', $this->page());
    }

    public function test_one_review_per_dish_per_order(): void
    {
        $order = $this->completedOrder();

        $this->submit(['order_token' => $order->token])->assertCreated();
        $this->submit(['order_token' => $order->token, 'comment' => 'Second try'])->assertStatus(422)->assertJsonValidationErrors('rating');

        $this->assertSame(1, Review::count());
    }

    public function test_order_links_that_do_not_qualify_are_rejected(): void
    {
        $other = MenuItem::factory()->create();
        $notFinished = $this->completedOrder();
        $notFinished->update(['status' => 'preparing']);

        $this->submit(['order_token' => $this->completedOrder($other)->token])->assertStatus(422)->assertJsonValidationErrors('order_token'); // dish not in that order
        $this->submit(['order_token' => $notFinished->token])->assertJsonValidationErrors('order_token');                                      // not completed
        $this->submit(['order_token' => str_repeat('a', 40)])->assertJsonValidationErrors('order_token');                                      // no such order
        $this->submit(['order_token' => 'short'])->assertJsonValidationErrors('order_token');                                                  // wrong length

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_the_moderation_setting(): void
    {
        $order = $this->completedOrder();

        config(['restaurant.reviews.moderation' => 'all']);
        $this->submit(['order_token' => $order->token])->assertCreated();
        $this->assertSame('pending', Review::firstOrFail()->status);

        Review::query()->delete();
        config(['restaurant.reviews.moderation' => 'none']);
        $this->submit(['comment' => 'Anonymous but trusted'])->assertCreated()->assertJsonStructure(['redirect']);
        $this->assertSame('approved', Review::firstOrFail()->status);
    }

    public function test_the_dish_page_unlocks_the_verified_form_from_an_order_link(): void
    {
        $order = $this->completedOrder();

        $this->get(route('menu.show', $this->item->slug) . '?order=' . $order->token)
            ->assertSee('verified buyer')->assertSee('name="order_token"', false)->assertSee('value="Rahim"', false);

        $this->get(route('menu.show', $this->item->slug) . '?order=' . str_repeat('b', 40))->assertDontSee('name="order_token"', false);

        $this->submit(['order_token' => $order->token])->assertCreated();
        $this->get(route('menu.show', $this->item->slug) . '?order=' . $order->token)
            ->assertSee('already reviewed this dish for this order')->assertDontSee('id="write-review"', false);
    }

    public function test_the_order_page_offers_a_rating_link_only_when_the_order_is_complete(): void
    {
        $order = $this->completedOrder();

        $this->get(route('orders.show', $order->token))
            ->assertSee('How was your food?')->assertSee('Rate this dish')->assertSee('?order=' . $order->token, false);

        $this->submit(['order_token' => $order->token])->assertCreated();
        $this->get(route('orders.show', $order->token))->assertSee('Reviewed')->assertDontSee('Rate this dish');

        $order->update(['status' => 'preparing']);
        $this->get(route('orders.show', $order->token))->assertDontSee('How was your food?');
    }

    // Replies

    public function test_a_visitor_reply_waits_for_approval(): void
    {
        $review = $this->review();

        $this->postJson(route('reviews.reply', $review), ['name' => 'Karim', 'body' => 'I agree completely'])
            ->assertCreated()->assertJsonStructure(['message']);

        $reply = ReviewReply::firstOrFail();
        $this->assertSame('pending', $reply->status);
        $this->assertFalse($reply->is_staff);
        $this->assertStringNotContainsString('I agree completely', $this->page());

        $this->actingAs($this->staff())->patch(route('admin.review-replies.status', $reply), ['status' => 'approved'])->assertSessionHas('status');
        $this->assertStringContainsString('I agree completely', $this->page());
    }

    public function test_replies_are_only_possible_on_live_reviews(): void
    {
        foreach (['pending', 'hidden'] as $status) {
            $review = $this->review(['status' => $status]);
            $this->postJson(route('reviews.reply', $review), ['name' => 'Karim', 'body' => 'Hello'])->assertNotFound();
        }

        $this->assertDatabaseCount('review_replies', 0);
    }

    public function test_reply_validation_and_rate_limit(): void
    {
        $review = $this->review();

        $this->postJson(route('reviews.reply', $review), [])->assertStatus(422)->assertJsonValidationErrors(['name', 'body']);
        $this->postJson(route('reviews.reply', $review), ['name' => 'K', 'body' => 'see www.spam.example'])->assertJsonValidationErrors('body');
        $this->postJson(route('reviews.reply', $review), ['name' => 'K', 'body' => 'ok', 'website' => 'x'])->assertJsonValidationErrors('website');
        $this->postJson(route('reviews.reply', $review), ['name' => 'K', 'body' => str_repeat('x', 601)])->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('review_replies', 0);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('reviews.reply', $review), ['name' => 'K', 'body' => "Reply {$i}"]);
        }
        $this->postJson(route('reviews.reply', $review), ['name' => 'K', 'body' => 'One more'])->assertStatus(429);
    }

    public function test_staff_replies_go_live_with_a_restaurant_badge(): void
    {
        $review = $this->review();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.reviews.reply', $review), ['body' => 'Thank you for visiting us!'])->assertSessionHas('status');

        $reply = ReviewReply::firstOrFail();
        $this->assertTrue($reply->is_staff);
        $this->assertSame('approved', $reply->status);
        $this->assertSame($admin->id, $reply->user_id);

        $html = $this->page();
        $this->assertStringContainsString('Thank you for visiting us!', $html);
        $this->assertStringContainsString('Restaurant', $html);
    }

    public function test_the_review_gets_its_own_share_links(): void
    {
        $review = $this->review();

        $html = $this->page();
        $this->assertStringContainsString('id="review-' . $review->id . '"', $html);
        $this->assertStringContainsString(rawurlencode(route('menu.show', $this->item->slug) . '#review-' . $review->id), $html);
        $this->assertStringContainsString('facebook.com/sharer', $html);
    }

    // Admin

    public function test_the_admin_list_filters(): void
    {
        $this->review(['name' => 'Pending Pete', 'status' => 'pending', 'rating' => 2]);
        $this->review(['name' => 'Live Laura', 'status' => 'approved', 'rating' => 5]);
        $staff = $this->staff();

        $this->actingAs($staff)->get('/admin/reviews')->assertOk()->assertSee('Pending Pete')->assertDontSee('Live Laura');
        $this->actingAs($staff)->get('/admin/reviews?status=approved')->assertSee('Live Laura')->assertDontSee('Pending Pete');
        $this->actingAs($staff)->get('/admin/reviews?status=all&rating=2')->assertSee('Pending Pete')->assertDontSee('Live Laura');
        $this->actingAs($staff)->get('/admin/reviews?status=all&q=Kacchi')->assertSee('Pending Pete')->assertSee('Live Laura');
        $this->actingAs($staff)->get('/admin/reviews?status=bogus')->assertSessionHasErrors('status');
        $this->actingAs($staff)->get(route('admin.reviews.show', Review::first()))->assertOk();
    }

    public function test_only_staff_can_moderate(): void
    {
        $review = $this->review();

        $this->get('/admin/reviews')->assertRedirect('/admin/login');
        $this->patch(route('admin.reviews.status', $review), ['status' => 'hidden'])->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->rider()->create())->get('/admin/reviews')->assertForbidden();
        $this->actingAs($this->staff())->patch(route('admin.reviews.status', $review), ['status' => 'nonsense'])->assertSessionHasErrors('status');
    }

    public function test_only_managers_can_delete_and_the_stats_follow(): void
    {
        $review = $this->review();
        $this->reply($review);
        app(ReviewService::class)->refresh($this->item->id);

        $this->actingAs($this->staff())->delete(route('admin.reviews.destroy', $review))->assertForbidden();
        $this->assertModelExists($review);

        $this->actingAs(User::factory()->manager()->create())->delete(route('admin.reviews.destroy', $review))->assertRedirect();

        $this->assertModelMissing($review);
        $this->assertDatabaseCount('review_replies', 0);
        $this->assertSame(0, $this->item->fresh()->reviews_count);
    }

    public function test_the_dashboard_counts_everything_waiting(): void
    {
        $live = $this->review();
        $this->review(['status' => 'pending']);
        $this->reply($live, ['status' => 'pending']);
        $this->reply($live, ['status' => 'pending']);
        $this->reply($live, ['status' => 'approved']);

        $this->actingAs($this->staff())->get('/admin')
            ->assertOk()->assertViewHas('stats', fn($stats) => collect($stats)->firstWhere('label', 'Reviews to approve')['value'] === 3);
    }

    public function test_a_deleted_dish_keeps_its_reviews_out_of_sight(): void
    {
        $review = $this->review();
        $this->item->delete();

        $this->get(route('menu.show', $this->item->slug))->assertNotFound();
        $this->postJson(route('reviews.reply', $review), ['name' => 'K', 'body' => 'Hello'])->assertNotFound();
        $this->actingAs($this->staff())->get(route('admin.reviews.show', $review))->assertOk()->assertSee('Kacchi Biryani');
    }
}
