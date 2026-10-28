<x-admin.layout title="Categories">
    <div class="mt-6 flex justify-end">
        <a href="{{ route('admin.categories.create') }}" class="btn-accent">Add category</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Name</th>
                    <th scope="col" class="p-4">Items</th>
                    <th scope="col" class="p-4">Order</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b border-navy/5">
                        <td class="p-4"><span
                                class="font-semibold text-navy">{{ $category->name_en }}</span><br><span
                                class="text-xs">{{ $category->name_bn }}</span></td>
                        <td class="p-4">{{ $category->items_count }}</td>
                        <td class="p-4">{{ $category->sort_order }}</td>
                        <td class="p-4">{{ $category->is_active ? 'Active' : 'Hidden' }}</td>
                        <td class="flex justify-end gap-3 p-4">
                            <a href="{{ route('admin.categories.edit', $category) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                data-confirm="Delete the category &quot;{{ $category->name_en }}&quot;?">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="font-semibold text-red-700 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $categories->links() }}</div>
</x-admin.layout>
