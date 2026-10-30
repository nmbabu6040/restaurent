<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function store(Request $request, MenuItem $item)
    {
        abort_unless($item->category()->where('is_active', true)->exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000', ReviewService::noLinks()],
            'order_token' => ['nullable', 'string', 'size:40'],
            'website' => ['nullable', 'max:0'],
        ]);

        $review = $this->reviews->submit($item, $data, app()->getLocale(), $request->ip());

        if ($review->status === 'approved') {
            // The changed query string makes the browser load the page again instead of just jumping to the anchor.
            $url = route('menu.show', $item->slug) . '?review=' . $review->id . '#review-' . $review->id;
            $message = __('reviews.thanks_live');

            return $request->wantsJson()
                ? response()->json(['redirect' => $url], 201)
                : redirect($url)->with('status', $message);
        }

        $message = __('reviews.thanks_pending');

        return $request->wantsJson()
            ? response()->json(['message' => $message], 201)
            : back()->with('status', $message);
    }

    public function reply(Request $request, Review $review)
    {
        $review->loadMissing('item');

        abort_unless(
            $review->status === 'approved'
                && $review->item
                && ! $review->item->trashed()
                && $review->item->category()->where('is_active', true)->exists(),
            404,
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:2', 'max:600', ReviewService::noLinks()],
            'website' => ['nullable', 'max:0'],
        ]);

        $reply = $this->reviews->visitorReply($review, $data, $request->ip());

        if ($reply->status === 'approved') {
            $url = route('menu.show', $review->item->slug) . '?reply=' . $reply->id . '#review-' . $review->id;

            return $request->wantsJson()
                ? response()->json(['redirect' => $url], 201)
                : redirect($url)->with('status', __('reviews.reply_live'));
        }

        return $request->wantsJson()
            ? response()->json(['message' => __('reviews.reply_pending')], 201)
            : back()->with('status', __('reviews.reply_pending'));
    }
}
