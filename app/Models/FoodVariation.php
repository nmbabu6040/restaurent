<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FoodVariation extends Model
{
    use Translatable, HasFactory;

    protected $fillable = ['menu_item_id', 'name_en', 'name_bn', 'price', 'sort_order', 'is_active', 'portion_factor', 'cost_price',];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean', 'portion_factor' => 'decimal:2', 'cost_price' => 'decimal:2'];
    }
}
