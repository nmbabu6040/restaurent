@if ($gallery->isNotEmpty())
    <x-site.section name="gallery" aria-labelledby="gallery-title">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <h2 id="gallery-title" class="text-center text-3xl font-bold">{{ $section->heading('home.gallery_title') }}
            </h2>
            <ul class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($gallery as $image)
                    <li class="overflow-hidden rounded-xl">
                        <x-site.picture :path="$image->image_path" :url="$image->image_url" :alt="$image->tr('alt')" :width="480"
                            :height="480" sizes="(min-width:768px) 25vw, 50vw"
                            class="aspect-square w-full object-cover transition duration-300 hover:scale-105" />
                    </li>
                @endforeach
            </ul>
        </div>
    </x-site.section>
@endif
