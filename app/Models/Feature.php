<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use Displayable, Translatable;

    protected $fillable = ['group', 'title_en', 'title_bn', 'text_en', 'text_bn', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
