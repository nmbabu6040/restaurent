<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class FoodAddon extends Model
{
    use Translatable;

    protected $fillable = ['menu_item_id', 'name_en', 'name_bn', 'price', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
