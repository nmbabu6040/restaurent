@php $brand = setting('name', config('app.name')); @endphp

<x-layouts.app :title="__('pages.about.title')" :description="__('pages.about.subtitle')">
    <x-site.page-header :title="__('pages.about.title')" :subtitle="__('pages.about.subtitle')" />

    <section class="mx-auto max-w-3xl px-6 py-14 text-center">
        <h2 class="text-3xl font-bold">{{ __('pages.about.story_title') }}</h2>
        <p class="mt-4 text-lg">{!! nl2br(e(setting('about_story', __('pages.about.story_text', ['name' => $brand])))) !!}</p>
    </section>

    <section class="mx-auto grid max-w-5xl gap-6 px-6 md:grid-cols-2">
        <div class="card p-8">
            <h2 class="text-2xl font-semibold">{{ __('pages.about.mission_title') }}</h2>
            <p class="mt-3">{{ __('pages.about.mission_text') }}</p>
        </div>
        <div class="card p-8">
            <h2 class="text-2xl font-semibold">{{ __('pages.about.vision_title') }}</h2>
            <p class="mt-3">{{ __('pages.about.vision_text') }}</p>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-6 py-14">
        <h2 class="text-center text-3xl font-bold">{{ __('pages.about.values_title') }}</h2>
        <ul class="mt-8 grid gap-8 md:grid-cols-3">
            @foreach (__('pages.about.values') as $value)
                <li class="text-center">
                    <h3 class="text-xl font-semibold">{{ $value['title'] }}</h3>
                    <p class="mt-2 text-sm">{{ $value['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($team->isNotEmpty())
        <section class="bg-surface py-14" aria-labelledby="team-title">
            <div class="mx-auto max-w-5xl px-6">
                <h2 id="team-title" class="text-center text-3xl font-bold">{{ __('pages.about.team_title') }}</h2>
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($team as $member)
                        <li class="rounded-2xl bg-warm p-6 text-center">
                            @if ($member->photo_url)
                                <img src="{{ $member->photo_url }}" alt="{{ $member->name }}" width="160"
                                    height="160" loading="lazy" class="mx-auto h-32 w-32 rounded-full object-cover">
                            @else
                                <div class="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-navy/10 font-heading text-5xl text-navy/40"
                                    aria-hidden="true">{{ mb_substr($member->name, 0, 1) }}</div>
                            @endif
                            <h3 class="mt-4 text-xl font-semibold">{{ $member->name }}</h3>
                            <p class="text-sm font-semibold text-terracotta-dark">{{ $member->tr('role') }}</p>
                            @if ($member->tr('bio'))
                                <p class="mt-3 text-sm">{{ $member->tr('bio') }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($gallery->isNotEmpty())
        <section class="mx-auto max-w-6xl px-6 py-14" aria-labelledby="space-title">
            <h2 id="space-title" class="text-center text-3xl font-bold">{{ __('pages.about.atmosphere_title') }}</h2>
            <ul class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($gallery as $image)
                    <li class="overflow-hidden rounded-xl">
                        <img src="{{ $image->image_url }}" alt="{{ $image->tr('alt') }}" width="480" height="480"
                            loading="lazy" class="aspect-square w-full object-cover">
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
