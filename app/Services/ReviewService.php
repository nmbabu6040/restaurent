<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /** Validation rule: no links in public text (the usual spam). */
    public static function noLinks(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|info|xyz|ru|cn|top|shop|site)\b)~i', (string) $value)) {
                $fail(__('reviews.errors.no_links'));
            }
        };
    }

    /** The finished order behind a "rate this dish" link, if it really contains this dish. */
    public function verifiedOrder(MenuItem $item, mixed $token): ?Order
    {
        if (! is_string($token) || strlen($token) !== 40) {
            return null;
        }

        return Order::where('token', $token)
            ->where('status', 'completed')
            ->whereHas('items', fn($q) => $q->where('menu_item_id', $item->id))
            ->first();
    }

    public function alreadyReviewed(Order $order, MenuItem $item): bool
    {
        return Review::where('order_id', $order->id)->where('menu_item_id', $item->id)->exists();
    }

    public function submit(MenuItem $item, array $data, string $locale, ?string $ip): Review
    {
        $order = null;

        if (filled($data['order_token'] ?? null)) {
            $order = $this->verifiedOrder($item, $data['order_token']);

            if (! $order) {
                throw ValidationException::withMessages(['order_token' => __('reviews.errors.bad_order')]);
            }

            if ($this->alreadyReviewed($order, $item)) {
                throw ValidationException::withMessages(['rating' => __('reviews.errors.already')]);
            }
        }

        $comment = filled($data['comment'] ?? null) ? trim($data['comment']) : null;

        if ($comment !== null && Review::where('menu_item_id', $item->id)->where('comment', $comment)->where('created_at', '>', now()->subDay())->exists()) {
            throw ValidationException::withMessages(['comment' => __('reviews.errors.duplicate')]);
        }

        try {
            $review = Review::create([
                'menu_item_id' => $item->id,
                'order_id' => $order?->id,
                'name' => trim($data['name']),
                'rating' => (int) $data['rating'],
                'comment' => $comment,
                'status' => $this->initialStatus($order !== null),
                'is_verified' => $order !== null,
                'locale' => $locale,
                'ip_address' => $ip,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['rating' => __('reviews.errors.already')]);
        }

        if ($review->status === 'approved') {
            $this->refresh($item->id);
        }

        return $review;
    }

    public function visitorReply(Review $review, array $data, ?string $ip): ReviewReply
    {
        return $review->replies()->create([
            'name' => trim($data['name']),
            'body' => trim($data['body']),
            'is_staff' => false,
            'status' => config('restaurant.reviews.moderation') === 'none' ? 'approved' : 'pending',
            'ip_address' => $ip,
        ]);
    }

    public function staffReply(Review $review, string $body, User $by): ReviewReply
    {
        $reply = $review->replies()->create([
            'name' => setting('name', config('app.name')),
            'body' => trim($body),
            'is_staff' => true,
            'user_id' => $by->id,
            'status' => 'approved',
        ]);

        ActivityLog::record('review.replied', "Replied to review #{$review->id}", $review);

        return $reply;
    }

    public function moderate(Review $review, string $status, User $by): void
    {
        $review->update(['status' => $status, 'moderated_by' => $by->id, 'moderated_at' => now()]);
        $this->refresh($review->menu_item_id);

        ActivityLog::record('review.' . $status, "Review #{$review->id} marked {$status}", $review);
    }

    public function moderateReply(ReviewReply $reply, string $status, User $by): void
    {
        $reply->update(['status' => $status]);

        ActivityLog::record('review.reply.' . $status, "Reply #{$reply->id} marked {$status}", $reply->review);
    }

    public function deleteReview(Review $review): void
    {
        $itemId = $review->menu_item_id;
        $review->delete();
        $this->refresh($itemId);

        ActivityLog::record('review.deleted', "Review #{$review->id} deleted");
    }

    /** Recounts the approved reviews of a dish and saves the average on the dish. */
    public function refresh(int $itemId): void
    {
        $row = Review::query()->where('menu_item_id', $itemId)->where('status', 'approved')
            ->toBase()->selectRaw('COUNT(*) as c, COALESCE(AVG(rating), 0) as a')->first();

        // The plain query builder: no timestamps change and no activity is logged.
        DB::table('menu_items')->where('id', $itemId)->update([
            'reviews_count' => (int) $row->c,
            'rating_avg' => round((float) $row->a, 2),
        ]);
    }

    private function initialStatus(bool $verified): string
    {
        return match (config('restaurant.reviews.moderation')) {
            'none' => 'approved',
            'all' => 'pending',
            default => $verified ? 'approved' : 'pending',
        };
    }
}
