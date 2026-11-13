@php
    $brand = setting('name', config('app.name'));
    $aboutTitle = setting('about_title', __('pages.about.title'));
    $aboutSubtitle = setting('about_subtitle', __('pages.about.subtitle'));
@endphp

<x-layouts.app :title="$aboutTitle" :description="$aboutSubtitle" :image="$space->first()?->image_url">
    <x-site.page-header :title="$aboutTitle" :subtitle="$aboutSubtitle" />

    <section class="mx-auto max-w-3xl px-6 py-14 text-center">
        <h2 class="text-3xl font-bold">{{ setting('about_story_title', __('pages.about.story_title')) }}</h2>
        <p class="mt-4 text-lg">{!! nl2br(e(setting('about_story', __('pages.about.story_text', ['name' => $brand])))) !!}</p>
    </section>

    @if (setting('owner_name'))
        @php $photo = setting_image('owner_photo'); @endphp
        <section class="mx-auto max-w-3xl px-6 pb-4" aria-labelledby="owner-title">
            <div class="card flex flex-col items-center gap-6 p-8 text-center sm:flex-row sm:text-left">
                @if ($photo)
                    <img src="{{ $photo }}" alt="{{ setting('owner_name') }}" width="160" height="160"
                        loading="lazy" class="h-32 w-32 shrink-0 rounded-full object-cover">
                @endif
                <div>
                    <h2 id="owner-title" class="text-2xl font-semibold">{{ setting('owner_name') }}</h2>
                    @if (setting('owner_title'))
                        <p class="text-sm font-semibold text-terracotta-dark">{{ setting('owner_title') }}</p>
                    @endif
                    @if (setting('owner_bio'))
                        <p class="mt-3 text-sm">{{ setting('owner_bio') }}</p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="mx-auto grid max-w-5xl gap-6 px-6 py-8 md:grid-cols-2">
        <div class="card p-8">
            <h2 class="text-2xl font-semibold">{{ setting('about_mission_title', __('pages.about.mission_title')) }}
            </h2>
            <p class="mt-3">{{ setting('about_mission', __('pages.about.mission_text')) }}</p>
        </div>
        <div class="card p-8">
            <h2 class="text-2xl font-semibold">{{ setting('about_vision_title', __('pages.about.vision_title')) }}</h2>
            <p class="mt-3">{{ setting('about_vision', __('pages.about.vision_text')) }}</p>
        </div>
    </section>

    @if ($values->isNotEmpty())
        <section class="mx-auto max-w-5xl px-6 py-14" aria-labelledby="values-title">
            <h2 id="values-title" class="text-center text-3xl font-bold">
                {{ setting('about_values_title', __('pages.about.values_title')) }}</h2>
            <ul class="mt-8 grid gap-8 md:grid-cols-3">
                @foreach ($values as $value)
                    <li class="text-center">
                        <h3 class="text-xl font-semibold">{{ $value->tr('title') }}</h3>
                        <p class="mt-2 text-sm">{{ $value->tr('text') }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($team->isNotEmpty())
        <section class="bg-surface py-14" aria-labelledby="team-title">
            <div class="mx-auto max-w-5xl px-6">
                <h2 id="team-title" class="text-center text-3xl font-bold">
                    {{ setting('about_team_title', __('pages.about.team_title')) }}</h2>
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

    @if ($space->isNotEmpty())
        <section class="mx-auto max-w-6xl px-6 py-14" aria-labelledby="space-title">
            <h2 id="space-title" class="text-center text-3xl font-bold">
                {{ setting('about_space_title', __('pages.about.atmosphere_title')) }}</h2>
            @if (setting('about_space_text'))
                <p class="mx-auto mt-3 max-w-2xl text-center">{{ setting('about_space_text') }}</p>
            @endif

            <ul class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3">
                @foreach ($space as $photo)
                    @php $caption = $photo->tr('caption'); @endphp
                    <li>
                        <figure>
                            <button type="button" data-lightbox data-src="{{ $photo->image_url }}"
                                data-alt="{{ $caption ?: $photo->tr('alt') }}"
                                aria-label="{{ __('pages.gallery.open') }}: {{ $photo->tr('alt') }}"
                                class="block w-full overflow-hidden rounded-xl">
                                <x-site.picture :path="$photo->image_path" :url="$photo->image_url" :alt="$photo->tr('alt')" :width="640"
                                    :height="480" sizes="(min-width:768px) 33vw, 50vw"
                                    class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-105" />
                            </button>
                            @if ($caption)
                                <figcaption class="mt-2 text-center text-sm">{{ $caption }}</figcaption>
                            @endif
                        </figure>
                    </li>
                @endforeach
            </ul>
        </section>

        <dialog id="lightbox" aria-label="{{ setting('about_space_title', __('pages.about.atmosphere_title')) }}"
            class="m-auto max-w-4xl rounded-2xl bg-surface p-0 backdrop:bg-black/70">
            <div class="p-4">
                <img src="" alt="" class="max-h-[75vh] w-full rounded-lg object-contain">
                <p data-lightbox-caption class="mt-3 text-center text-sm"></p>
                <div class="mt-3 text-center">
                    <button type="button" data-lightbox-close
                        class="btn-primary">{{ __('pages.gallery.close') }}</button>
                </div>
            </div>
        </dialog>
    @endif
</x-layouts.app>
