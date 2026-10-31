<x-layouts.app :title="$page->tr('meta_title') ?: $page->tr('title')" :description="$page->tr('meta_description') ?: \Illuminate\Support\Str::limit(trim(strip_tags($page->html())), 155)">
    <x-site.page-header :title="$page->tr('title')" />
    <article class="prose-lite mx-auto max-w-3xl px-6 py-12">{!! $page->html() !!}</article>
</x-layouts.app>
