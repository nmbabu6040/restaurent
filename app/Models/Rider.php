<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rider extends Model
{
    use HasFactory, SoftDeletes;

    public const VEHICLES = [
        'bike' => 'Motorbike',
        'bicycle' => 'Bicycle',
        'scooter' => 'Scooter',
        'car' => 'Car',
        'walking' => 'On foot',
    ];

    protected $fillable = ['user_id', 'name', 'phone', 'vehicle', 'vehicle_number', 'fee_per_delivery', 'is_active', 'is_online', 'notes'];

    protected function casts(): array
    {
        return ['fee_per_delivery' => 'decimal:2', 'is_active' => 'boolean', 'is_online' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function vehicleLabel(): string
    {
        return self::VEHICLES[$this->vehicle] ?? ucfirst($this->vehicle);
    }
}
