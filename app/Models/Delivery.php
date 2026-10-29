<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'rider_id',
        'status',
        'rider_fee',
        'cash_collected',
        'assigned_by',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'failed_reason',
        'settled_at',
        'settled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'rider_fee' => 'decimal:2',
            'cash_collected' => 'decimal:2',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Kept even after a rider is removed, so old deliveries and settlements still show a name. */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class)->withTrashed();
    }
}
