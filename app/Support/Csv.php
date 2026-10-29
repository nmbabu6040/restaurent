<?php

namespace App\Support;

final class Csv
{
    /** Stops spreadsheet programs from running text such as =HYPERLINK(...) as a formula. */
    public static function cell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
