<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;

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

    public function show(MenuItem $item)
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

        return view('menu.show', [
            'item' => $item->load('category', 'activeVariations', 'activeAddons'),
            'related' => $related,
        ]);
    }
}
