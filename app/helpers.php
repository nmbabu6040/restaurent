<?php

use App\Models\RestaurantSetting;

use Illuminate\Support\Facades\Storage;

if (! function_exists('setting')) {
    function setting(string $key, ?string $default = null): ?string
    {
        return RestaurantSetting::get($key, $default);
    }
}

if (! function_exists('money')) {
    /** Format an amount in currency units, e.g. 380 => "৳ 380". */
    function money(int|float|string $amount): string
    {
        $amount = (float) $amount;
        $whole = abs($amount - round($amount)) < 0.005;

        return config('restaurant.currency_symbol') . ' ' . number_format($amount, $whole ? 0 : 2);
    }
}

if (! function_exists('money_c')) {
    /** Format an amount given in paisa (integer cents). */
    function money_c(int $cents): string
    {
        return money($cents / 100);
    }
}

if (! function_exists('image_thumb')) {
    /** URL of the 480px thumbnail made by ImageService, or null for older uploads that have none. */
    function image_thumb(?string $path): ?string
    {
        if (! $path || ! str_ends_with($path, '.webp')) {
            return null;
        }

        $thumb = substr($path, 0, -5) . '-480.webp';
        $disk = Storage::disk('public');

        return $disk->exists($thumb) ? $disk->url($thumb) : null;
    }
}
