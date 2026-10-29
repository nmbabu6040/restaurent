<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuCategory extends Model
{
    use HasFactory, SoftDeletes, Translatable, LogsActivity;

    protected $fillable = [
        'name_en',
        'name_bn',
        'slug',
        'description_en',
        'description_bn',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn() => $this->tr('name'));
    }

    protected function description(): Attribute
    {
        return Attribute::get(fn() => $this->tr('description'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
