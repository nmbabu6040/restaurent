<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_subtotal',
        'max_discount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Hidden';
        }

        if ($this->starts_at?->isFuture()) {
            return 'Scheduled';
        }

        if ($this->ends_at?->isPast()) {
            return 'Expired';
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'Used up';
        }

        return 'Live';
    }
}
