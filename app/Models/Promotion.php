<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Promotion extends Model
{
    use Displayable, Translatable;

    protected $fillable = [
        'title_en',
        'title_bn',
        'description_en',
        'description_bn',
        'image_path',
        'starts_at',
        'ends_at',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn() => $this->image_path ? Storage::disk('public')->url($this->image_path) : null
        );
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->active()
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
