<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'description', 'subject_type', 'subject_id', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $description, ?Model $subject = null): void
    {
        static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()->ip(),
        ]);
    }
}
