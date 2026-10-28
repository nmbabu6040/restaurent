<x-admin.layout title="Newsletter subscribers">
    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.subscribers.index') }}" role="search"
            class="flex flex-wrap items-end gap-3">
            <div>
                <label for="q" class="block text-sm font-medium text-navy">Search email</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-navy">Status</label>
                <select id="status" name="status"
                    class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="unsubscribed" @selected(request('status') === 'unsubscribed')>Unsubscribed</option>
                </select>
            </div>
            <button type="submit" class="btn-primary !py-2">Filter</button>
        </form>
        <a href="{{ route('admin.subscribers.export') }}" class="btn-accent">Export active (CSV)</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Email</th>
                    <th scope="col" class="p-4">Language</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4">Subscribed</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscribers as $s)
                    <tr class="border-b border-navy/5">
                        <td class="p-4">{{ $s->email }}</td>
                        <td class="p-4">{{ strtoupper($s->locale) }}</td>
                        <td class="p-4">{{ $s->unsubscribed_at ? 'Unsubscribed' : 'Active' }}</td>
                        <td class="p-4">{{ $s->subscribed_at?->format('d M Y') ?? '—' }}</td>
                        <td class="p-4 text-right">
                            <form method="POST" action="{{ route('admin.subscribers.destroy', $s) }}"
                                data-confirm="Remove {{ $s->email }} from the list?">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="font-semibold text-red-700 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center">No subscribers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $subscribers->links() }}</div>
</x-admin.layout>
