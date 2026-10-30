<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'menu_item_id',
        'order_id',
        'name',
        'rating',
        'comment',
        'status',
        'is_verified',
        'locale',
        'ip_address',
        'moderated_by',
        'moderated_at',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_verified' => 'boolean', 'moderated_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class)->orderBy('id');
    }

    public function approvedReplies(): HasMany
    {
        return $this->replies()->where('status', 'approved');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
