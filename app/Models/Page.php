<?php

namespace App\Models;

use App\Models\Concerns\Displayable;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Page extends Model
{
    use Displayable, LogsActivity, Translatable;

    protected $fillable = [
        'slug',
        'title_en',
        'title_bn',
        'body_en',
        'body_bn',
        'meta_title_en',
        'meta_title_bn',
        'meta_description_en',
        'meta_description_bn',
        'show_in_header',
        'show_in_footer',
        'is_system',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['show_in_header' => 'boolean', 'show_in_footer' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget('pages.menu');
            Cache::forget('sitemap.xml');
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /** Markdown to safe HTML: raw HTML in the text is dropped and unsafe links are removed. */
    public function html(): string
    {
        return Str::markdown((string) $this->tr('body'), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /** Pages shown as links in the header or footer. */
    public static function menu(string $place): Collection
    {
        $rows = Cache::rememberForever('pages.menu.v2', fn() => static::active()->ordered()->get([
            'id',
            'slug',
            'title_en',
            'title_bn',
            'show_in_header',
            'show_in_footer',
        ])
            ->toArray());

        return static::hydrate($rows)->where($place === 'header' ? 'show_in_header' : 'show_in_footer', true)
            ->values();
    }
}
