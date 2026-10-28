<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Support\Facades\Storage;

class TeamMember extends Model
{
    use Displayable, Translatable, LogsActivity;

    protected $fillable = ['name', 'role_en', 'role_bn', 'bio_en', 'bio_bn', 'photo_path', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(
            fn() => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null
        );
    }
}
