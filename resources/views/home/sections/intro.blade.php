<x-site.section name="intro" aria-labelledby="intro-title">
    <div class="mx-auto max-w-5xl px-6 py-16 text-center">
        <h2 id="intro-title" class="text-3xl font-bold">{{ $section->heading('home.intro_title') }}</h2>
        <p class="mx-auto mt-4 max-w-2xl">{{ $section->tr('body') ?: __('home.intro_text') }}</p>
        @if ($features->isNotEmpty())
            <ul class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ($features as $feature)
                    <li>
                        <h3 class="text-xl font-semibold introH3">{{ $feature->tr('title') }}</h3>
                        <p class="mt-2 text-sm">{{ $feature->tr('text') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-site.section>
