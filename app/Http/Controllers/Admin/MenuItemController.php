<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:name_en,price,sort_order,created_at'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $sort = $filters['sort'] ?? 'sort_order';
        $dir = $filters['dir'] ?? 'asc';

        $items = MenuItem::query()
            ->with('category')
            ->when(filled($filters['q'] ?? null), fn($b) => $b->search($filters['q']))
            ->when(filled($filters['category'] ?? null), fn($b) => $b->where('menu_category_id', $filters['category']))
            ->orderBy($sort, $dir)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.items.index', [
            'items' => $items,
            'categories' => MenuCategory::ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.items.form', [
            'item' => new MenuItem(['is_available' => true, 'sort_order' => 0]),
            'categories' => MenuCategory::ordered()->get(),
        ]);
    }

    public function store(MenuItemRequest $request)
    {
        $data = $this->payload($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('menu', 'public');
        }

        MenuItem::create($data);

        return redirect()->route('admin.items.index')->with('status', 'Menu item created.');
    }

    public function edit(MenuItem $item)
    {
        return view('admin.items.form', [
            'item' => $item,
            'categories' => MenuCategory::ordered()->get(),
        ]);
    }

    public function update(MenuItemRequest $request, MenuItem $item)
    {
        $data = $this->payload($request);

        if ($request->hasFile('image')) {
            $this->deleteImage($item->image_path);
            $data['image_path'] = $request->file('image')->store('menu', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($item->image_path);
            $data['image_path'] = null;
        }

        $item->update($data);

        return redirect()->route('admin.items.index')->with('status', 'Menu item updated.');
    }

    public function destroy(MenuItem $item)
    {
        $item->delete(); // soft delete: the image file is kept in case the item is restored

        return redirect()->route('admin.items.index')->with('status', 'Menu item deleted.');
    }

    public function toggleAvailability(Request $request, MenuItem $item)
    {
        $data = $request->validate(['is_available' => ['required', 'boolean']]);

        $item->update(['is_available' => $data['is_available']]);

        return response()->json([
            'message' => $item->name_en . ' marked ' . ($item->is_available ? 'available' : 'sold out') . '.',
        ]);
    }

    private function payload(MenuItemRequest $request): array
    {
        $data = Arr::except($request->validated(), ['image', 'remove_image']);
        $data['dietary'] = array_values(array_filter($data['dietary'] ?? []));
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
