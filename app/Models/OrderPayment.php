<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    public const METHODS = ['cash' => 'Cash', 'card' => 'Card', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'other' => 'Other'];

    protected $fillable = ['order_id', 'method', 'amount', 'tendered', 'reference', 'user_id', 'cash_session_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tendered' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
