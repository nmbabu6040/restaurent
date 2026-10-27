<?php

use App\Models\RestaurantSetting;

if (! function_exists('setting')) {
    function setting(string $key, ?string $default = null): ?string
    {
        return RestaurantSetting::get($key, $default);
    }
}
