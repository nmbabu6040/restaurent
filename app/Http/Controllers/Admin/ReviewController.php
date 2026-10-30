<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'hidden', 'all'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $f['status'] ?? 'pending';

        $reviews = Review::query()
            ->with('item:id,name_en,slug')
            ->withCount(['replies as pending_replies_count' => fn($q) => $q->where('status', 'pending')])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($f['rating'] ?? null, fn($q, $r) => $q->where('rating', $r))
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)
                    ->orWhere('comment', 'like', $like)
                    ->orWhereHas('item', fn($i) => $i->where('name_en', 'like', $like)));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'status' => $status,
            'counts' => Review::query()->toBase()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
            'pendingReplies' => ReviewReply::where('status', 'pending')->count(),
        ]);
    }

    public function show(Review $review)
    {
        return view('admin.reviews.show', ['review' => $review->load('item', 'order:id,reference', 'replies')]);
    }

    public function status(Request $request, Review $review)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'hidden'])]]);

        $this->reviews->moderate($review, $data['status'], $request->user());

        return back()->with('status', "Review marked {$data['status']}.");
    }

    public function reply(Request $request, Review $review)
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:600']]);

        $this->reviews->staffReply($review, $data['body'], $request->user());

        return back()->with('status', 'Your reply is posted.');
    }

    public function replyStatus(Request $request, ReviewReply $reply)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'hidden'])]]);

        $this->reviews->moderateReply($reply, $data['status'], $request->user());

        return back()->with('status', "Reply marked {$data['status']}.");
    }

    public function destroy(Review $review)
    {
        $this->reviews->deleteReview($review);

        return redirect()->route('admin.reviews.index')->with('status', 'Review deleted.');
    }

    public function destroyReply(ReviewReply $reply)
    {
        $reply->delete();

        return back()->with('status', 'Reply deleted.');
    }
}
