<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\RecipeLine;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IngredientController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'low' => ['nullable', 'boolean']]);

        $ingredients = Ingredient::query()
            ->with('supplier')
            ->when(filled($filters['q'] ?? null), fn($q) => $q->where('name', 'like', '%' . addcslashes($filters['q'], '%_\\') . '%'))
            ->when($request->boolean('low'), fn($q) => $q->low())
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $all = Ingredient::query()->get(['id', 'stock', 'unit_cost']);

        return view('admin.ingredients.index', [
            'ingredients' => $ingredients,
            'total' => $all->count(),
            'lowCount' => Ingredient::low()->count(),
            'stockValue' => $all->sum(fn($i) => max(0, (float) $i->stock) * (float) $i->unit_cost),
        ]);
    }

    public function create()
    {
        return view('admin.ingredients.form', [
            'ingredient' => new Ingredient(['is_active' => true, 'unit' => 'kg', 'stock' => 0, 'reorder_level' => 0, 'unit_cost' => 0]),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(null, true));
        $opening = (float) ($data['opening_stock'] ?? 0);
        unset($data['opening_stock']);

        $ingredient = Ingredient::create($data + ['stock' => 0]);

        if ($opening > 0) {
            $this->inventory->setCount($ingredient, $opening, 'Opening stock');
        }

        ActivityLog::record('ingredient.created', "Ingredient {$ingredient->name} created", $ingredient);

        return redirect()->route('admin.ingredients.show', $ingredient)->with('status', 'Ingredient created.');
    }

    public function show(Ingredient $ingredient)
    {
        return view('admin.ingredients.show', [
            'ingredient' => $ingredient->load('supplier'),
            'movements' => $ingredient->movements()->with('user')->latest('id')->paginate(15),
            'dishes' => MenuItem::whereIn('id', RecipeLine::where('ingredient_id', $ingredient->id)->pluck('menu_item_id'))->orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    public function edit(Ingredient $ingredient)
    {
        return view('admin.ingredients.form', [
            'ingredient' => $ingredient,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $ingredient->update($request->validate($this->rules($ingredient->id, false)));

        ActivityLog::record('ingredient.updated', "Ingredient {$ingredient->name} updated", $ingredient);

        return redirect()->route('admin.ingredients.show', $ingredient)->with('status', 'Ingredient updated.');
    }

    public function destroy(Ingredient $ingredient)
    {
        if (RecipeLine::where('ingredient_id', $ingredient->id)->exists()) {
            return back()->with('error', 'This ingredient is used in a recipe. Remove it from the recipes first.');
        }

        $ingredient->delete();
        ActivityLog::record('ingredient.deleted', "Ingredient {$ingredient->name} deleted");

        return redirect()->route('admin.ingredients.index')->with('status', 'Ingredient deleted.');
    }

    public function receive(Request $request, Ingredient $ingredient)
    {
        $data = $request->validateWithBag('receive', [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:10000000', 'decimal:0,3'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,4'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $this->inventory->receive(
            $ingredient,
            (float) $data['quantity'],
            isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
            $data['note'] ?? null,
        );

        ActivityLog::record('stock.received', "Received {$data['quantity']} {$ingredient->unit} of {$ingredient->name}", $ingredient);

        return back()->with('status', 'Stock received.');
    }

    public function waste(Request $request, Ingredient $ingredient)
    {
        $data = $request->validateWithBag('waste', [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:10000000', 'decimal:0,3'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $this->inventory->waste($ingredient, (float) $data['quantity'], $data['note'] ?? null);

        ActivityLog::record('stock.wasted', "Wasted {$data['quantity']} {$ingredient->unit} of {$ingredient->name}", $ingredient);

        return back()->with('status', 'Wastage recorded.');
    }

    public function count(Request $request, Ingredient $ingredient)
    {
        $data = $request->validateWithBag('count', [
            'counted' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,3'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $movement = $this->inventory->setCount($ingredient, (float) $data['counted'], $data['note'] ?? 'Stock count');

        if ($movement) {
            ActivityLog::record('stock.counted', "Stock of {$ingredient->name} set to {$data['counted']} {$ingredient->unit}", $ingredient);
        }

        return back()->with('status', $movement ? 'Stock updated.' : 'The count matches the current stock. Nothing changed.');
    }

    private function rules(?int $id, bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120', Rule::unique('ingredients', 'name')->whereNull('deleted_at')->ignore($id)],
            'unit' => ['required', Rule::in(array_keys(Ingredient::UNITS))],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,3'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,4'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($creating) {
            $rules['opening_stock'] = ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,3'];
        }

        return $rules;
    }
}
