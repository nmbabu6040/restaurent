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

if (! function_exists('section_theme')) {
    /** Background color of a frontend section plus whether it needs light text. */
    function section_theme(string $key): array
    {
        $default = \App\Support\Sections::all()[$key]['default'] ?? '#FAF7F2';
        $custom = setting('color_' . $key);
        $bg = \App\Support\Color::isHex($custom) ? strtoupper($custom) : $default;

        return ['bg' => $bg, 'dark' => \App\Support\Color::wantsLightText($bg)];
    }
}

if (! function_exists('setting_image')) {
    /** Public URL of an image stored in a setting (logo, favicon, owner photo), or null. */
    function setting_image(string $key): ?string
    {
        $path = setting($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('whatsapp_link')) {
    /** wa.me link from the WhatsApp settings, or null when the button is off or the number is missing. */
    function whatsapp_link(?string $text = null): ?string
    {
        if (setting('whatsapp_enabled') !== '1') {
            return null;
        }

        $number = preg_replace('/\D/', '', (string) setting('whatsapp_number'));

        if (strlen($number) < 8) {
            return null;
        }

        $text ??= setting('whatsapp_message');

        return 'https://wa.me/' . $number . ($text ? '?text=' . rawurlencode($text) : '');
    }
}
