<x-site.section :bg="$section->bg_color" aria-labelledby="section-{{ $section->id }}">
    <div class="mx-auto max-w-3xl px-6 py-14 text-center">
        <h2 id="section-{{ $section->id }}" class="text-3xl font-bold">{{ $section->tr('title') }}</h2>
        @if ($section->tr('subtitle'))
            <p class="mt-2 text-lg">{{ $section->tr('subtitle') }}</p>
        @endif
        @if ($section->tr('body'))
            <div class="prose-lite mt-4 text-left">{!! $section->bodyHtml() !!}</div>
        @endif
        @if ($section->button_url && $section->tr('button_text'))
            <a href="{{ $section->button_url }}" class="btn-contrast mt-6">{{ $section->tr('button_text') }}</a>
        @endif
    </div>
</x-site.section>
