<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, Translatable;

    protected $fillable = [
        'stock_mode',
        'stock_qty',
        'cost_price',
        'menu_category_id',
        'name_en',
        'name_bn',
        'slug',
        'description_en',
        'description_bn',
        'ingredients_en',
        'ingredients_bn',
        'allergens_en',
        'allergens_bn',
        'price',
        'image_path',
        'dietary',
        'is_available',
        'is_featured',
        'is_special',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'dietary' => 'array',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'is_special' => 'boolean',
            'stock_qty' => 'integer',
            'cost_price' => 'decimal:2',
            'reviews_count' => 'integer',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /** Ingredient cost of one standard portion, from the recipe. */
    public function recipeCost(): float
    {
        $this->loadMissing('recipeLines.ingredient');

        return (float) $this->recipeLines->sum(fn($l) => (float) $l->quantity * (float) ($l->ingredient?->unit_cost ?? 0));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(FoodVariation::class)->orderBy('sort_order')->orderBy('id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(FoodAddon::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariations(): HasMany
    {
        return $this->variations()->where('is_active', true);
    }

    public function activeAddons(): HasMany
    {
        return $this->addons()->where('is_active', true);
    }

    public function hasOptions(): bool
    {
        return $this->activeVariations->isNotEmpty() || $this->activeAddons->isNotEmpty();
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn() => $this->tr('name'));
    }

    protected function description(): Attribute
    {
        return Attribute::get(fn() => $this->tr('description'));
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn() => $this->image_path ? Storage::disk('public')->url($this->image_path) : null
        );
    }

    /** "From ৳ 250" when the item has sizes (requires the activeVariations relation to be loaded), else the price. */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(function () {
            if ($this->relationLoaded('activeVariations') && $this->activeVariations->isNotEmpty()) {
                return __('orders.from', ['price' => money($this->activeVariations->min('price'))]);
            }

            return money($this->price);
        });
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(function (Builder $w) use ($like) {
            $w->where('name_en', 'like', $like)
                ->orWhere('name_bn', 'like', $like)
                ->orWhere('description_en', 'like', $like)
                ->orWhere('description_bn', 'like', $like);
        });
    }
}
