<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\Translatable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use Displayable, Translatable, LogsActivity;

    protected $fillable = ['name', 'title_en', 'title_bn', 'quote_en', 'quote_bn', 'rating', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rating' => 'integer'];
    }
}
