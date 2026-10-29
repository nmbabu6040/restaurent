<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use Translatable;

    protected $fillable = [
        'menu_item_id',
        'name_en',
        'name_bn',
        'variation_en',
        'variation_bn',
        'addons',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'addons' => 'array',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** e.g. "Full, + Extra egg, + Borhani" in the given language (falls back to English). */
    public function optionsLabel(string $locale = 'en'): string
    {
        $pick = fn(?string $bn, ?string $en) => ($locale === 'bn' && filled($bn)) ? $bn : $en;
        $parts = [];

        if (filled($this->variation_en)) {
            $parts[] = $pick($this->variation_bn, $this->variation_en);
        }

        foreach ($this->addons ?? [] as $addon) {
            $parts[] = '+ ' . $pick($addon['name_bn'] ?? null, $addon['name_en'] ?? '');
        }

        return implode(', ', $parts);
    }
}
