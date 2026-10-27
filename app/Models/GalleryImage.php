<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    use Displayable, Translatable;

    protected $fillable = ['image_path', 'title_en', 'title_bn', 'alt_en', 'alt_bn', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn() => Storage::disk('public')->url($this->image_path));
    }
}
