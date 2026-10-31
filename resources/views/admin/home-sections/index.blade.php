<x-admin.layout title="Home page sections">
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm">Change the order with the arrows, hide a section, or edit its heading. Top to bottom is the
            order on the home page.</p>
        <a href="{{ route('admin.home-sections.create') }}" class="btn-accent">Add a custom section</a>
    </div>

    <ul class="mt-4 space-y-3">
        @foreach ($sections as $section)
            <li class="card flex flex-wrap items-center gap-3 p-4 {{ $section->is_visible ? '' : 'opacity-70' }}">
                <div class="flex flex-col">
                    <form method="POST" action="{{ route('admin.home-sections.move', [$section, 'up']) }}">@csrf
                        <button type="submit" @disabled($loop->first) aria-label="Move {{ $section->label() }} up"
                            class="rounded p-1 text-navy hover:bg-navy/10 disabled:opacity-30">&#9650;</button>
                    </form>
                    <form method="POST" action="{{ route('admin.home-sections.move', [$section, 'down']) }}">@csrf
                        <button type="submit" @disabled($loop->last)
                            aria-label="Move {{ $section->label() }} down"
                            class="rounded p-1 text-navy hover:bg-navy/10 disabled:opacity-30">&#9660;</button>
                    </form>
                </div>
                <div class="min-w-48 flex-1">
                    <p class="font-semibold text-navy">{{ $section->label() }} @if ($section->is_custom)
                            <span class="ml-1 rounded-full bg-navy/10 px-2 py-0.5 text-xs font-normal">custom</span>
                        @endif
                    </p>
                    <p class="text-xs">
                        {{ $section->title_en ?: ($section->is_custom ? '' : 'Uses the built-in heading') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.home-sections.toggle', $section) }}">@csrf
                    <button type="submit" aria-pressed="{{ $section->is_visible ? 'true' : 'false' }}"
                        class="rounded-full px-3 py-1 text-xs font-semibold {{ $section->is_visible ? 'bg-green-800 text-white' : 'bg-charcoal text-white' }}">{{ $section->is_visible ? 'Shown' : 'Hidden' }}</button>
                </form>
                <a href="{{ route('admin.home-sections.edit', $section) }}"
                    class="font-semibold text-navy hover:text-terracotta-dark">Edit</a>
                @if ($section->is_custom)
                    <form method="POST" action="{{ route('admin.home-sections.destroy', $section) }}"
                        data-confirm="Delete this section?">@csrf @method('DELETE')
                        <button type="submit" class="font-semibold text-red-700 hover:underline">Delete</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    <p class="mt-4 text-sm">The hero photo and overlay are in <a href="{{ route('admin.appearance.edit') }}"
            class="font-semibold text-navy underline">Appearance & site</a>, its headline in Settings. Section
        background colors are in Appearance too. The "Introduction" highlights are in <a
            href="{{ route('admin.content.index', 'usps') }}" class="font-semibold text-navy underline">Home
            highlights</a>.</p>
</x-admin.layout>
