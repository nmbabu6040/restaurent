<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'reference',
        'token',
        'type',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'subtotal',
        'delivery_fee',
        'discount',
        'coupon_code',
        'total',
        'payment_method',
        'payment_status',
        'status',
        'status_changed_at',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'status_changed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** New orders the kitchen should act on: cash orders, or online orders that are already paid. */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where(fn($w) => $w->where('payment_method', 'cod')->orWhere('payment_status', 'paid'));
    }

    /** Call inside a DB transaction on a locked row. Also gives a used coupon back. */
    public function markCancelled(): void
    {
        $this->update(['status' => OrderStatus::Cancelled, 'status_changed_at' => now()]);

        if ($this->coupon_code) {
            Coupon::where('code', $this->coupon_code)->where('used_count', '>', 0)->decrement('used_count');
        }

        // Give the stock back (dish counts and ingredients) that this order took.
        app(\App\Services\InventoryService::class)->restore($this->loadMissing('items'));
    }
}
