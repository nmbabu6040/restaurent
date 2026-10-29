<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'name',
        'email',
        'phone',
        'reserved_at',
        'duration_minutes',
        'party_size',
        'message',
        'status',
        'status_changed_at',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'status' => ReservationStatus::class,
        ];
    }

    protected function endsAt(): Attribute
    {
        return Attribute::get(fn() => $this->reserved_at->copy()->addMinutes($this->duration_minutes));
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Pending->value,
            ReservationStatus::Confirmed->value,
        ]);
    }
}
