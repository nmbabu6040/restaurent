@props(['title', 'subtitle' => null])

<header class="bg-surface py-14 text-center">
    <div class="mx-auto max-w-3xl px-6">
        <h1 class="text-4xl font-bold sm:text-5xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-3">{{ $subtitle }}</p>
        @endif
    </div>
</header>
