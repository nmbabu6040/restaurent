<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FoodAddon extends Model
{
    use Translatable, HasFactory;

    protected $fillable = ['menu_item_id', 'name_en', 'name_bn', 'price', 'sort_order', 'cost_price', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean', 'cost_price' => 'decimal:2'];
    }
}
