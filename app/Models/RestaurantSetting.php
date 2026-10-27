<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RestaurantSetting extends Model
{
    protected $fillable = ['key', 'value_en', 'value_bn'];

    protected static function booted(): void
    {
        static::saved(fn() => Cache::forget('restaurant_settings'));
        static::deleted(fn() => Cache::forget('restaurant_settings'));
    }

    public static function cached(): array
    {
        return Cache::rememberForever('restaurant_settings', function () {
            return static::query()->get()->mapWithKeys(fn($s) => [
                $s->key => ['en' => $s->value_en, 'bn' => $s->value_bn],
            ])->all();
        });
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::cached()[$key] ?? null;

        if (! $row) {
            return $default;
        }

        $value = $row[app()->getLocale()] ?? null;

        return filled($value) ? $value : (filled($row['en']) ? $row['en'] : $default);
    }
}
