<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReply extends Model
{
    protected $fillable = ['review_id', 'name', 'body', 'is_staff', 'user_id', 'status', 'ip_address'];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
