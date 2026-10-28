<x-admin.layout title="Activity log">
    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">When</th>
                    <th scope="col" class="p-4">Who</th>
                    <th scope="col" class="p-4">Action</th>
                    <th scope="col" class="p-4">Details</th>
                    <th scope="col" class="p-4">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-navy/5">
                        <td class="whitespace-nowrap p-4">{{ $log->created_at?->format('d M Y, g:i A') }}</td>
                        <td class="p-4">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="p-4 font-mono text-xs">{{ $log->action }}</td>
                        <td class="p-4">{{ $log->description }}</td>
                        <td class="p-4 text-xs">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center">No activity yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>
</x-admin.layout>
