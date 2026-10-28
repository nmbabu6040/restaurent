@php
    $sortLink = fn(string $col) => route(
        'admin.items.index',
        array_merge(request()->except('page'), [
            'sort' => $col,
            'dir' => request('sort', 'sort_order') === $col && request('dir', 'asc') === 'asc' ? 'desc' : 'asc',
        ]),
    );
@endphp

<x-admin.layout title="Menu items">
    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.items.index') }}" class="flex flex-wrap items-end gap-3"
            role="search">
            <div>
                <label for="q" class="block text-sm font-medium text-navy">Search</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="category" class="block text-sm font-medium text-navy">Category</label>
                <select id="category" name="category"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((int) request('category') === $c->id)>{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary !py-2">Filter</button>
            @if (request()->hasAny(['q', 'category']))
                <a href="{{ route('admin.items.index') }}"
                    class="py-2 text-sm font-semibold text-navy hover:text-terracotta-dark">Reset</a>
            @endif
        </form>
        <a href="{{ route('admin.items.create') }}" class="btn-accent">Add menu item</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4"><span class="sr-only">Image</span></th>
                    <th scope="col" class="p-4"><a href="{{ $sortLink('name_en') }}"
                            class="font-semibold hover:text-terracotta-dark">Name</a></th>
                    <th scope="col" class="p-4">Category</th>
                    <th scope="col" class="p-4"><a href="{{ $sortLink('price') }}"
                            class="font-semibold hover:text-terracotta-dark">Price</a></th>
                    <th scope="col" class="p-4">Available</th>
                    <th scope="col" class="p-4">Flags</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-b border-navy/5">
                        <td class="p-4">
                            @if ($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" width="48" height="48"
                                    loading="lazy" class="h-12 w-12 rounded-lg object-cover">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-navy/10 font-heading text-navy/40"
                                    aria-hidden="true">{{ mb_substr($item->name_en, 0, 1) }}</div>
                            @endif
                        </td>
                        <td class="p-4"><span class="font-semibold text-navy">{{ $item->name_en }}</span><br><span
                                class="text-xs">{{ $item->name_bn }}</span></td>
                        <td class="p-4">{{ $item->category?->name_en ?? '—' }}</td>
                        <td class="p-4">{{ $item->formatted_price }}</td>
                        <td class="p-4">
                            <input type="checkbox" role="switch" class="h-5 w-5 accent-navy"
                                data-toggle-url="{{ route('admin.items.availability', $item) }}"
                                aria-label="Available: {{ $item->name_en }}" @checked($item->is_available)>
                        </td>
                        <td class="p-4 text-xs">
                            @if ($item->is_featured)
                                <span class="rounded-full bg-terracotta-dark px-2 py-0.5 text-white">Featured</span>
                            @endif
                            @if ($item->is_special)
                                <span class="rounded-full bg-navy px-2 py-0.5 text-white">Special</span>
                            @endif
                        </td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.items.edit', $item) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                                <form method="POST" action="{{ route('admin.items.destroy', $item) }}"
                                    data-confirm="Delete &quot;{{ $item->name_en }}&quot;? It will disappear from the website.">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="font-semibold text-red-700 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center">No menu items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
</x-admin.layout>
