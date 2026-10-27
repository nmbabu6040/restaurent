@if ($items->isEmpty())
    <div class="card mx-auto max-w-md p-10 text-center">
        <p class="font-heading text-xl text-navy">{{ __('menu.empty_title') }}</p>
        <p class="mt-2 text-sm">{{ __('menu.empty_text') }}</p>
    </div>
@else
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <x-menu.item-card :item="$item" />
        @endforeach
    </div>

    @if ($items->hasPages())
        <nav aria-label="Pagination" class="mt-10 flex items-center justify-center gap-4 text-sm">
            @if ($items->onFirstPage())
                <span class="btn border border-navy/10 text-navy/40" aria-disabled="true">{{ __('menu.prev') }}</span>
            @else
                <a href="{{ $items->previousPageUrl() }}" data-ajax-page
                    class="btn border border-navy/20 text-navy hover:border-terracotta">{{ __('menu.prev') }}</a>
            @endif

            <span>{{ __('menu.page_of', ['page' => $items->currentPage(), 'last' => $items->lastPage()]) }}</span>

            @if ($items->hasMorePages())
                <a href="{{ $items->nextPageUrl() }}" data-ajax-page
                    class="btn border border-navy/20 text-navy hover:border-terracotta">{{ __('menu.next') }}</a>
            @else
                <span class="btn border border-navy/10 text-navy/40" aria-disabled="true">{{ __('menu.next') }}</span>
            @endif
        </nav>
    @endif
@endif
