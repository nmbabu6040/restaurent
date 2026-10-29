<x-layouts.app :title="__('pages.gallery.title')" :description="__('pages.gallery.subtitle')">
    <x-site.page-header :title="__('pages.gallery.title')" :subtitle="__('pages.gallery.subtitle')" />

    <section class="mx-auto max-w-7xl px-6 py-12">
        @if ($images->isEmpty())
            <p class="text-center">{{ __('pages.gallery.empty') }}</p>
        @else
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($images as $image)
                    <li>
                        <button type="button" data-lightbox data-src="{{ $image->image_url }}"
                            data-alt="{{ $image->tr('alt') }}"
                            aria-label="{{ __('pages.gallery.open') }}: {{ $image->tr('alt') }}"
                            class="block w-full overflow-hidden rounded-xl">
                            <x-site.picture :path="$image->image_path" :url="$image->image_url" :alt="$image->tr('alt')" :width="480"
                                :height="480" sizes="(min-width:1024px) 25vw, (min-width:768px) 33vw, 50vw"
                                class="aspect-square w-full object-cover transition duration-300 hover:scale-105" />
                        </button>
                    </li>
                @endforeach
            </ul>

            @if ($images->hasPages())
                <nav aria-label="Pagination" class="mt-10 flex items-center justify-center gap-4 text-sm">
                    @if ($images->onFirstPage())
                        <span class="btn border border-navy/10 text-navy/40"
                            aria-disabled="true">{{ __('menu.prev') }}</span>
                    @else
                        <a href="{{ $images->previousPageUrl() }}"
                            class="btn border border-navy/20 text-navy hover:border-terracotta">{{ __('menu.prev') }}</a>
                    @endif
                    <span>{{ __('menu.page_of', ['page' => $images->currentPage(), 'last' => $images->lastPage()]) }}</span>
                    @if ($images->hasMorePages())
                        <a href="{{ $images->nextPageUrl() }}"
                            class="btn border border-navy/20 text-navy hover:border-terracotta">{{ __('menu.next') }}</a>
                    @else
                        <span class="btn border border-navy/10 text-navy/40"
                            aria-disabled="true">{{ __('menu.next') }}</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>

    <dialog id="lightbox" aria-label="{{ __('pages.gallery.title') }}"
        class="m-auto max-w-4xl rounded-2xl bg-surface p-0 backdrop:bg-black/70">
        <div class="p-4">
            <img src="" alt="" class="max-h-[75vh] w-full rounded-lg object-contain">
            <p data-lightbox-caption class="mt-3 text-center text-sm"></p>
            <div class="mt-3 text-center">
                <button type="button" data-lightbox-close class="btn-primary">{{ __('pages.gallery.close') }}</button>
            </div>
        </div>
    </dialog>
</x-layouts.app>
