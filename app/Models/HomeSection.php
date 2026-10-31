<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HomeSection extends Model
{
    use Translatable;

    public const SYSTEM = [
        'hero' => 'Hero (top banner)',
        'categories' => 'Categories',
        'featured' => 'Signature dishes',
        'intro' => 'Introduction and highlights',
        'chef' => "Chef's recommendations",
        'offers' => 'Special offers',
        'gallery' => 'Gallery',
        'testimonials' => 'Testimonials',
        'visit' => 'Hours and location',
        'reserve' => 'Reservation banner',
    ];

    protected $fillable = [
        'key',
        'is_custom',
        'title_en',
        'title_bn',
        'subtitle_en',
        'subtitle_bn',
        'body_en',
        'body_bn',
        'button_text_en',
        'button_text_bn',
        'button_url',
        'bg_color',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return ['is_custom' => 'boolean', 'is_visible' => 'boolean'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function label(): string
    {
        return $this->is_custom ? ($this->title_en ?: 'Custom section') : (self::SYSTEM[$this->key] ?? $this->key);
    }

    /** The title typed in the admin, or the built-in wording when it is empty. */
    public function heading(string $langKey): string
    {
        return $this->tr('title') ?: __($langKey);
    }

    public function bodyHtml(): string
    {
        return Str::markdown((string) $this->tr('body'), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
