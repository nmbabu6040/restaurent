<?php

namespace App\Support;

final class Color
{
    public static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(function (string $c) {
            $v = hexdec($c) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** WCAG contrast ratio between two colors (1 to 21). */
    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** True when white text reads better on this background than dark text. */
    public static function wantsLightText(string $bg): bool
    {
        return self::contrast($bg, '#FFFFFF') >= self::contrast($bg, '#292D32');
    }

    /** The best contrast any text color (white or charcoal) can reach on this background. */
    public static function bestContrast(string $bg): float
    {
        return max(self::contrast($bg, '#FFFFFF'), self::contrast($bg, '#292D32'));
    }
}
