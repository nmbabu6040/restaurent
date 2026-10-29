<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;

    public const UNITS = ['g' => 'grams (g)', 'kg' => 'kilograms (kg)', 'ml' => 'millilitres (ml)', 'l' => 'litres (l)', 'pcs' => 'pieces (pcs)'];

    protected $fillable = ['name', 'unit', 'stock', 'reorder_level', 'unit_cost', 'supplier_id', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /** Active ingredients at or below their reorder level. */
    public function scopeLow(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereColumn('stock', '<=', 'reorder_level');
    }

    public function isLow(): bool
    {
        return $this->is_active && (float) $this->stock <= (float) $this->reorder_level;
    }
}
