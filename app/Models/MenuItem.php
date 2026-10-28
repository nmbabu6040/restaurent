<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use SoftDeletes, Translatable, LogsActivity;

    protected $fillable = [
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
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
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

    protected function formattedPrice(): Attribute
    {
        return Attribute::get(function () {
            $price = (float) $this->price;
            $number = fmod($price, 1) === 0.0 ? number_format($price, 0) : number_format($price, 2);

            return config('restaurant.currency_symbol') . ' ' . $number;
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
