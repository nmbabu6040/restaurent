<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use Displayable, Translatable;

    protected $fillable = ['question_en', 'question_bn', 'answer_en', 'answer_bn', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
