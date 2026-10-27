<?php

namespace App\Models\Concerns;

trait Translatable
{
    public function tr(string $field): ?string
    {
        $value = $this->{$field . '_' . app()->getLocale()} ?? null;

        return filled($value) ? $value : $this->{$field . '_en'};
    }
}
