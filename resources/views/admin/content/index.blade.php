@php $img = $cfg['image'] ?? null; @endphp

<x-admin.layout :title="$cfg['title']">
    <div class="mt-6 flex justify-end">
        <a href="{{ route('admin.content.create', $key) }}" class="btn-accent">Add {{ $cfg['singular'] }}</a>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    @if ($img)
                        <th scope="col" class="p-4"><span class="sr-only">Image</span></th>
                    @endif
                    @foreach ($cfg['columns'] as $header)
                        <th scope="col" class="p-4">{{ $header }}</th>
                    @endforeach
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-b border-navy/5">
                        @if ($img)
                            <td class="p-4">
                                @if ($record->{$img['accessor']})
                                    <img src="{{ $record->{$img['accessor']} }}" alt="" width="48"
                                        height="48" loading="lazy" class="h-12 w-12 rounded-lg object-cover">
                                @endif
                            </td>
                        @endif
                        @foreach ($cfg['columns'] as $col => $header)
                            @php $v = $record->{$col}; @endphp
                            <td class="p-4">
                                @if ($v instanceof \DateTimeInterface)
                                    {{ $v->format('d M Y, g:i A') }}
                                @elseif (blank($v))
                                    —
                                @else
                                    {{ \Illuminate\Support\Str::limit((string) $v, 60) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="p-4">{{ $record->is_active ? 'Visible' : 'Hidden' }}</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.content.edit', [$key, $record->id]) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                                <form method="POST" action="{{ route('admin.content.destroy', [$key, $record->id]) }}"
                                    data-confirm="Delete this {{ $cfg['singular'] }}? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="font-semibold text-red-700 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-6 text-center">Nothing here yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $records->links() }}</div>
</x-admin.layout>
