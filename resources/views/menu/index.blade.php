<x-layouts.app :title="__('menu.title')" :description="__('menu.subtitle')">
    <section id="menu-root" class="mx-auto max-w-7xl px-6 py-14">
        <header class="text-center">
            <h1 class="text-4xl font-bold sm:text-5xl">{{ __('menu.title') }}</h1>
            <p class="mx-auto mt-3 max-w-xl">{{ __('menu.subtitle') }}</p>
        </header>

        <form id="menu-filter" method="GET" action="{{ route('menu.index') }}" role="search"
            class="mx-auto mt-10 flex max-w-xl gap-2">
            <input type="hidden" name="category" value="{{ $activeCategory }}">
            <label for="menu-search" class="sr-only">{{ __('menu.search_label') }}</label>
            <input id="menu-search" type="search" name="q" value="{{ $search }}" maxlength="100"
                placeholder="{{ __('menu.search_placeholder') }}"
                class="w-full rounded-full border border-navy/15 bg-surface px-5 py-3 text-sm focus:border-terracotta">
            <button type="submit" class="btn-primary shrink-0">{{ __('menu.search_button') }}</button>
        </form>

        <nav aria-label="{{ __('menu.categories') }}" class="mt-6 flex flex-wrap justify-center gap-2">
            <a href="{{ route('menu.index', array_filter(['q' => $search])) }}" data-category=""
                aria-current="{{ $activeCategory === '' ? 'true' : 'false' }}" class="chip">{{ __('menu.all') }}</a>
            @foreach ($categories as $category)
                <a href="{{ route('menu.index', array_filter(['q' => $search, 'category' => $category->slug])) }}"
                    data-category="{{ $category->slug }}"
                    aria-current="{{ $activeCategory === $category->slug ? 'true' : 'false' }}"
                    class="chip">{{ $category->name }}</a>
            @endforeach
        </nav>

        <div id="menu-grid" aria-busy="false" class="mt-10 transition-opacity">
            <p id="menu-status" class="sr-only" role="status" aria-live="polite"></p>
            @include('menu._grid')
        </div>
    </section>
</x-layouts.app>
