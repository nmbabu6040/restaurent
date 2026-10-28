<x-admin.layout title="Users">
    <div class="mt-6 flex justify-end">
        <a href="{{ route('admin.users.create') }}" class="btn-accent">Add user</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Name</th>
                    <th scope="col" class="p-4">Role</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr class="border-b border-navy/5">
                        <td class="p-4"><span class="font-semibold text-navy">{{ $u->name }}</span><br><span
                                class="text-xs">{{ $u->email }}</span></td>
                        <td class="p-4">{{ ucfirst($u->role?->value ?? '—') }}</td>
                        <td class="p-4">{{ $u->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $u) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                                @if (!$u->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                        data-confirm="Delete {{ $u->email }}?">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="font-semibold text-red-700 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>
</x-admin.layout>
