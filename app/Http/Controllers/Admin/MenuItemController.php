<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuItemRequest;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
            'item' => new MenuItem(['is_available' => true, 'sort_order' => 0, 'stock_mode' => 'none', 'stock_qty' => 0, 'cost_price' => 0]),
            'categories' => MenuCategory::ordered()->get(),
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(MenuItemRequest $request)
    {
        $data = $this->payload($request, true);

        if ($request->hasFile('image')) {
            $data['image_path'] = app(ImageService::class)->store($request->file('image'), 'menu');
        }

        DB::transaction(function () use ($data, $request) {
            $item = MenuItem::create($data);
            $this->syncOptions($item, $request->validated());
            $this->syncRecipe($item, $request);
        });

        return redirect()->route('admin.items.index')->with('status', 'Menu item created.');
    }

    public function edit(MenuItem $item)
    {
        return view('admin.items.form', [
            'item' => $item->load('recipeLines.ingredient'),
            'categories' => MenuCategory::ordered()->get(),
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function update(MenuItemRequest $request, MenuItem $item)
    {
        $data = $this->payload($request, false);

        if ($request->hasFile('image')) {
            $this->deleteImage($item->image_path);
            $data['image_path'] = app(ImageService::class)->store($request->file('image'), 'menu');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($item->image_path);
            $data['image_path'] = null;
        }

        DB::transaction(function () use ($item, $data, $request) {
            $item->update($data);
            $this->syncOptions($item, $request->validated());
            $this->syncRecipe($item, $request);
        });

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

    private function payload(MenuItemRequest $request, bool $creating): array
    {
        $data = Arr::except($request->validated(), ['image', 'remove_image', 'variations', 'addons', 'recipe', 'recipe_form']);
        $data['dietary'] = array_values(array_filter($data['dietary'] ?? []));
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($creating) {
            $data['stock_mode'] = $data['stock_mode'] ?? 'none';
        }

        foreach (['stock_qty' => 0, 'cost_price' => 0] as $field => $default) {
            if (array_key_exists($field, $data) || $creating) {
                $data[$field] = $data[$field] ?? $default;
            }
        }

        return $data;
    }

    /**
     * Save sizes and extras. Rows with an id are updated in place (so carts keep working),
     * new rows are created, and rows missing from the form are deleted.
     */
    private function syncOptions(MenuItem $item, array $validated): void
    {
        foreach (['variations', 'addons'] as $key) {
            $relation = fn() => $key === 'variations' ? $item->variations() : $item->addons();
            $keep = [];

            foreach (($validated[$key] ?? []) as $position => $row) {
                $attributes = [
                    'name_en' => $row['name_en'],
                    'name_bn' => $row['name_bn'] ?? null,
                    'price' => $row['price'],
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'sort_order' => $position,
                ];

                if ($key === 'variations') {
                    $attributes['portion_factor'] = $row['portion_factor'] ?? 1;
                    $attributes['cost_price'] = $row['cost_price'] ?? null;
                } else {
                    $attributes['cost_price'] = $row['cost_price'] ?? 0;
                }

                $model = ! empty($row['id']) ? $relation()->whereKey($row['id'])->first() : null;

                if ($model) {
                    $model->update($attributes);
                } else {
                    $model = $relation()->create($attributes);
                }

                $keep[] = $model->id;
            }

            $relation()->whereNotIn('id', $keep)->delete();
        }
    }

    /** Only touched when the form says it sent the recipe section (an empty list then means "remove all"). */
    private function syncRecipe(MenuItem $item, MenuItemRequest $request): void
    {
        if (! $request->boolean('recipe_form')) {
            return;
        }

        $rows = collect($request->validated('recipe') ?? []);

        foreach ($rows as $row) {
            $item->recipeLines()->updateOrCreate(
                ['ingredient_id' => $row['ingredient_id']],
                ['quantity' => $row['quantity']],
            );
        }

        $item->recipeLines()->whereNotIn('ingredient_id', $rows->pluck('ingredient_id'))->delete();
    }

    private function deleteImage(?string $path): void
    {
        app(ImageService::class)->delete($path);
    }
}
