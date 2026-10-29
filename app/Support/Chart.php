<?php

namespace App\Support;

/** Builds the small JSON configs that resources/js/modules/charts.js turns into Chart.js charts. */
final class Chart
{
    private static function make(string $type, array $labels, array $datasets, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'labels' => array_values($labels),
            'datasets' => $datasets,
            'symbol' => config('restaurant.currency_symbol'),
            'money' => false,
        ], $extra);
    }

    /** @param array<int, array{label:string, data:array, color?:string}> $datasets */
    public static function line(array $labels, array $datasets, bool $money = false): array
    {
        return self::make('line', $labels, $datasets, ['money' => $money]);
    }

    public static function bar(array $labels, array $datasets, bool $money = false, bool $horizontal = false, bool $stacked = false): array
    {
        return self::make('bar', $labels, $datasets, ['money' => $money, 'horizontal' => $horizontal, 'stacked' => $stacked]);
    }

    public static function doughnut(array $labels, array $values, string $label, array $colors = [], bool $money = false): array
    {
        return self::make('doughnut', $labels, [['label' => $label, 'data' => array_values($values), 'colors' => array_values($colors)]], ['money' => $money]);
    }

    public static function round(array $values): array
    {
        return array_map(fn($v) => round((float) $v, 2), array_values($values));
    }
}
