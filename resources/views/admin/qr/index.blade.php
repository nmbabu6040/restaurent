<x-admin.layout title="QR codes">
    <form method="GET" action="{{ route('admin.qr.index') }}" class="card mt-6 flex flex-wrap items-end gap-4 p-5">
        <div>
            <label for="target" class="block text-sm font-medium text-navy">Where should it lead?</label>
            <select id="target" name="target"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                @foreach ($targets as $key => $t)
                    <option value="{{ $key }}" @selected($target === $key)>{{ $t[0] }}</option>
                @endforeach
                <option value="custom" @selected($target === 'custom')>Another page on this site…</option>
            </select>
        </div>
        <div>
            <label for="path" class="block text-sm font-medium text-navy">Page path (for "another page")</label>
            <input id="path" name="path" value="{{ $path }}" placeholder="/offers" maxlength="100"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            <p class="mt-1 text-sm text-red-700">{{ $errors->first('path') }}</p>
        </div>
        <div>
            <label for="label" class="block text-sm font-medium text-navy">Text on the sign</label>
            <input id="label" name="label" value="{{ $label }}" maxlength="80"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn-primary !py-2">Update</button>
    </form>

    <div class="card mt-6 flex items-center gap-8 p-6">
        <div class="w-30% shrink-0 rounded-lg bg-white p-3" role="img"
            aria-label="QR code that opens {{ $url }}">{!! $svg !!}</div>
        <div class="w-70%  flex-1">
            <p class="text-sm">This code opens:</p>
            <p class="break-all font-semibold text-navy">{{ $url }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.qr.download', request()->query()) }}" class="btn-primary">Download SVG</a>
                <a href="{{ route('admin.qr.print', array_merge(request()->query(), ['autoprint' => 1])) }}"
                    target="_blank" rel="noopener" class="btn-accent">Print a sign</a>
            </div>
            <p class="mt-4 text-xs font-justify">SVG stays sharp at any size, so it is good for posters and table
                stands. Test the
                printed code with your phone before you print many copies. The code only works while the website address
                stays the same.
            </p>
        </div>
    </div>
</x-admin.layout>
