<x-admin.layout title="Pages">
    <div class="mt-6 flex justify-end"><a href="{{ route('admin.pages.create') }}" class="btn-accent">Add page</a></div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Page</th>
                    <th scope="col" class="p-4">Address</th>
                    <th scope="col" class="p-4">Shown in</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr class="border-b border-navy/5">
                        <td class="p-4 font-semibold text-navy">{{ $page->title_en }}@if ($page->is_system)
                                <span class="ml-1 rounded-full bg-navy/10 px-2 py-0.5 text-xs font-normal">built
                                    in</span>
                            @endif
                        </td>
                        <td class="p-4 font-mono text-xs">
                            {{ $page->is_system ? '/' . $page->slug : '/page/' . $page->slug }}</td>
                        <td class="p-4">
                            {{ collect([$page->show_in_header ? 'Header' : null, $page->show_in_footer ? 'Footer' : null])->filter()->implode(', ') ?:'—' }}
                        </td>
                        <td class="p-4">{{ $page->is_active ? 'Visible' : 'Hidden' }}</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ $page->is_system ? url('/' . $page->slug) : route('pages.show', $page->slug) }}"
                                    target="_blank" rel="noopener"
                                    class="font-semibold text-navy hover:text-terracotta-dark">View</a>
                                <a href="{{ route('admin.pages.edit', $page) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                                @unless ($page->is_system)
                                    <form method="POST" action="{{ route('admin.pages.destroy', $page) }}"
                                        data-confirm="Delete the page &quot;{{ $page->title_en }}&quot;?">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="font-semibold text-red-700 hover:underline">Delete</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center">No pages yet. Run <code>php artisan db:seed
                                    --class=PageSeeder</code> for Privacy and Terms.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $pages->links() }}</div>
    </x-admin.layout>
