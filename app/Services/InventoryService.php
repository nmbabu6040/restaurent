<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** Cost of one portion: the recipe cost (scaled by the size) or the cost price typed in by the admin, plus add-on costs. */
    public function unitCost(MenuItem $item, $variation, Collection $addons): float
    {
        $item->loadMissing('recipeLines.ingredient');

        if ($item->stock_mode === 'recipe' && $item->recipeLines->isNotEmpty()) {
            $base = $item->recipeCost() * (float) ($variation?->portion_factor ?? 1);
        } else {
            $base = (float) ($variation?->cost_price ?? $item->cost_price);
        }

        return $base + (float) $addons->sum(fn($a) => (float) $a->cost_price);
    }

    /**
     * Checks and takes stock for a new order. Must run inside the checkout transaction: a shortage throws
     * a ValidationException and everything (order, coupon use, stock) is rolled back.
     *
     * @return array<string, array{unit_cost: float, consumed: ?array}> keyed by cart line key
     */
    public function apply(Order $order, Collection $lines): array
    {
        $plan = [];
        $dishNeeds = [];
        $ingredientNeeds = [];
        $dishNames = [];

        foreach ($lines as $line) {
            $item = $line['item'];
            $item->loadMissing('recipeLines.ingredient');

            $plan[$line['key']] = [
                'unit_cost' => round($this->unitCost($item, $line['variation'], $line['addons']), 2),
                'consumed' => null,
            ];

            if ($item->stock_mode === 'quantity') {
                $dishNeeds[$item->id] = ($dishNeeds[$item->id] ?? 0) + $line['qty'];
                $plan[$line['key']]['consumed'] = ['mode' => 'quantity', 'qty' => $line['qty']];
            } elseif ($item->stock_mode === 'recipe' && $item->recipeLines->isNotEmpty()) {
                $factor = (float) ($line['variation']?->portion_factor ?? 1);
                $used = [];

                foreach ($item->recipeLines as $recipe) {
                    $amount = round((float) $recipe->quantity * $factor * $line['qty'], 3);
                    $used[$recipe->ingredient_id] = $amount;
                    $ingredientNeeds[$recipe->ingredient_id] = ($ingredientNeeds[$recipe->ingredient_id] ?? 0) + $amount;
                    $dishNames[$recipe->ingredient_id][$item->name] = true;
                }

                $plan[$line['key']]['consumed'] = ['mode' => 'recipe', 'ingredients' => $used];
            }
        }

        // Rows are locked in id order so two checkouts at the same moment cannot deadlock.
        ksort($dishNeeds);
        foreach ($dishNeeds as $itemId => $need) {
            $row = MenuItem::whereKey($itemId)->lockForUpdate()->first();
            $left = (int) ($row?->stock_qty ?? 0);

            if (! $row || $left < $need) {
                throw ValidationException::withMessages([
                    'cart' => __('orders.errors.stock', ['item' => $row?->name ?? '', 'count' => max(0, $left)]),
                ]);
            }

            $remaining = $left - $need;
            $row->update($remaining === 0 ? ['stock_qty' => 0, 'is_available' => false] : ['stock_qty' => $remaining]);
        }

        ksort($ingredientNeeds);
        foreach ($ingredientNeeds as $ingredientId => $need) {
            $ingredient = Ingredient::withTrashed()->whereKey($ingredientId)->lockForUpdate()->first();

            if (! $ingredient) {
                continue;
            }

            if (config('restaurant.inventory.enforce_recipe_stock') && (float) $ingredient->stock < $need) {
                throw ValidationException::withMessages([
                    'cart' => __('orders.errors.out_of_stock', ['items' => implode(', ', array_keys($dishNames[$ingredientId]))]),
                ]);
            }

            $balance = round((float) $ingredient->stock - $need, 3);
            $ingredient->update(['stock' => $balance]);

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => 'sale',
                'quantity' => -$need,
                'unit_cost' => $ingredient->unit_cost,
                'balance_after' => $balance,
                'order_id' => $order->id,
                'note' => "Order {$order->reference}",
            ]);
        }

        return $plan;
    }

    /** Gives back what a cancelled order took. Safe to call twice: it forgets what it restored. */
    public function restore(Order $order): void
    {
        foreach ($order->items as $line) {
            $consumed = $line->consumed;

            if (! is_array($consumed)) {
                continue;
            }

            if (($consumed['mode'] ?? null) === 'quantity' && $line->menu_item_id) {
                $item = MenuItem::withTrashed()->whereKey($line->menu_item_id)->lockForUpdate()->first();

                if ($item && $item->stock_mode === 'quantity') {
                    $before = (int) $item->stock_qty;
                    $item->update([
                        'stock_qty' => $before + (int) ($consumed['qty'] ?? 0),
                        'is_available' => $before === 0 ? true : $item->is_available, // it was hidden when the count reached zero
                    ]);
                }
            } elseif (($consumed['mode'] ?? null) === 'recipe') {
                foreach (($consumed['ingredients'] ?? []) as $ingredientId => $amount) {
                    $ingredient = Ingredient::withTrashed()->whereKey((int) $ingredientId)->lockForUpdate()->first();

                    if (! $ingredient) {
                        continue;
                    }

                    $balance = round((float) $ingredient->stock + (float) $amount, 3);
                    $ingredient->update(['stock' => $balance]);

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'type' => 'return',
                        'quantity' => (float) $amount,
                        'unit_cost' => $ingredient->unit_cost,
                        'balance_after' => $balance,
                        'order_id' => $order->id,
                        'user_id' => auth()->id(),
                        'note' => "Order {$order->reference} cancelled",
                    ]);
                }
            }

            $line->update(['consumed' => null]);
        }
    }

    /** Adds purchased stock and updates the weighted-average cost. */
    public function receive(Ingredient $ingredient, float $quantity, ?float $unitCost, ?string $note): StockMovement
    {
        return DB::transaction(function () use ($ingredient, $quantity, $unitCost, $note) {
            $locked = Ingredient::whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
            $stock = (float) $locked->stock;
            $balance = round($stock + $quantity, 3);
            $attributes = ['stock' => $balance];

            if ($unitCost !== null) {
                $attributes['unit_cost'] = $stock > 0
                    ? round((($stock * (float) $locked->unit_cost) + ($quantity * $unitCost)) / ($stock + $quantity), 4)
                    : round($unitCost, 4);
            }

            $locked->update($attributes);

            return StockMovement::create([
                'ingredient_id' => $locked->id,
                'type' => 'purchase',
                'quantity' => $quantity,
                'unit_cost' => $unitCost ?? $locked->unit_cost,
                'balance_after' => $balance,
                'user_id' => auth()->id(),
                'note' => $note,
            ]);
        });
    }

    /** Removes spoiled or wasted stock. It counts as a cost in the profit and loss report. */
    public function waste(Ingredient $ingredient, float $quantity, ?string $note): StockMovement
    {
        return DB::transaction(function () use ($ingredient, $quantity, $note) {
            $locked = Ingredient::whereKey($ingredient->id)->lockForUpdate()->firstOrFail();

            if ($quantity > (float) $locked->stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'You only have ' . qty($locked->stock) . ' ' . $locked->unit . ' in stock.',
                ])->errorBag('waste');
            }

            $balance = round((float) $locked->stock - $quantity, 3);
            $locked->update(['stock' => $balance]);

            return StockMovement::create([
                'ingredient_id' => $locked->id,
                'type' => 'waste',
                'quantity' => -$quantity,
                'unit_cost' => $locked->unit_cost,
                'balance_after' => $balance,
                'user_id' => auth()->id(),
                'note' => $note,
            ]);
        });
    }

    /** Sets the stock to a counted amount. Returns null when nothing changed. */
    public function setCount(Ingredient $ingredient, float $counted, ?string $note): ?StockMovement
    {
        return DB::transaction(function () use ($ingredient, $counted, $note) {
            $locked = Ingredient::whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
            $difference = round($counted - (float) $locked->stock, 3);

            if ($difference == 0.0) {
                return null;
            }

            $locked->update(['stock' => round($counted, 3)]);

            return StockMovement::create([
                'ingredient_id' => $locked->id,
                'type' => 'adjustment',
                'quantity' => $difference,
                'unit_cost' => $locked->unit_cost,
                'balance_after' => round($counted, 3),
                'user_id' => auth()->id(),
                'note' => $note,
            ]);
        });
    }
}
