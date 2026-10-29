<?php

namespace App\Support;

final class ExpenseCategories
{
    /** Ingredient purchases are not listed on purpose: record them with "Receive stock" instead. */
    public static function all(): array
    {
        return [
            'rent' => 'Rent',
            'salaries' => 'Salaries and wages',
            'utilities' => 'Utilities (electricity, gas, water)',
            'supplies' => 'Supplies (non-food, cleaning)',
            'packaging' => 'Packaging',
            'marketing' => 'Marketing',
            'maintenance' => 'Repairs and maintenance',
            'delivery' => 'Delivery and courier',
            'fees' => 'Fees, taxes and licences',
            'other' => 'Other',
        ];
    }
}
