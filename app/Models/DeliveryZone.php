<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    use Displayable, LogsActivity, Translatable;

    protected $fillable = ['name_en', 'name_bn', 'fee', 'min_order', 'free_over', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'min_order' => 'decimal:2',
            'free_over' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
