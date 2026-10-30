<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Services\ReviewService;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($data['q'] ?? '');
        $activeCategory = $data['category'] ?? '';

        $items = MenuItem::query()
            ->with(['category', 'activeVariations', 'activeAddons'])
            ->whereHas('category', function ($q) use ($activeCategory) {
                $q->where('is_active', true)
                    ->when($activeCategory !== '', fn($c) => $c->where('slug', $activeCategory));
            })
            ->when($search !== '', fn($q) => $q->search($search))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(9)
            ->withQueryString();

        if ($request->ajax()) {
            return view('menu._grid', compact('items'));
        }

        return view('menu.index', [
            'items' => $items,
            'categories' => MenuCategory::active()->ordered()->get(),
            'search' => $search,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(Request $request, MenuItem $item, ReviewService $reviewService)
    {
        abort_unless($item->category()->where('is_active', true)->exists(), 404);

        $related = MenuItem::query()
            ->with(['category', 'activeVariations', 'activeAddons'])
            ->where('menu_category_id', $item->menu_category_id)
            ->where('id', '!=', $item->id)
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        // A "rate this dish" link from the customer's finished order unlocks a verified-buyer review.
        $orderForReview = $reviewService->verifiedOrder($item, $request->query('order'));
        $alreadyReviewed = $orderForReview !== null && $reviewService->alreadyReviewed($orderForReview, $item);

        return view('menu.show', [
            'item' => $item->load('category', 'activeVariations', 'activeAddons'),
            'related' => $related,
            'reviews' => Review::where('menu_item_id', $item->id)->approved()
                ->with('approvedReplies')->latest('id')
                ->paginate(config('restaurant.reviews.per_page'), ['*'], 'reviews')->fragment('reviews'),
            'distribution' => Review::where('menu_item_id', $item->id)->approved()->toBase()
                ->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating')->all(),
            'orderForReview' => $alreadyReviewed ? null : $orderForReview,
            'alreadyReviewed' => $alreadyReviewed,
        ]);
    }
}
