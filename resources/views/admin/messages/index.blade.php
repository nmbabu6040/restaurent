<x-admin.layout title="Messages">
    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">From</th>
                    <th scope="col" class="p-4">Subject</th>
                    <th scope="col" class="p-4">Received</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($messages as $m)
                    <tr class="border-b border-navy/5 {{ $m->read_at ? '' : 'bg-terracotta/5 font-semibold' }}">
                        <td class="p-4">{{ $m->name }}<br><span
                                class="text-xs font-normal">{{ $m->email }}</span></td>
                        <td class="p-4">{{ $m->subject ?: \Illuminate\Support\Str::limit($m->message, 60) }}</td>
                        <td class="p-4">{{ $m->created_at->format('d M Y, g:i A') }}</td>
                        <td class="p-4 text-right"><a href="{{ route('admin.messages.show', $m) }}"
                                class="text-navy hover:text-terracotta-dark">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-6 text-center">No messages yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $messages->links() }}</div>
</x-admin.layout>
