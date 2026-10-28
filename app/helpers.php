<?php

use App\Models\RestaurantSetting;

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
