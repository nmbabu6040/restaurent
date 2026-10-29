<form method="GET" action="{{ $action }}" role="search" aria-label="Report period"
    class="card mt-6 flex flex-wrap items-end gap-3 p-4">
    <div>
        <label for="range" class="block text-sm font-medium text-navy">Period</label>
        <select id="range" name="range" data-range-select
            class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            @foreach ($presets as $key => $label)
                <option value="{{ $key }}" @selected($p['preset'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="from" class="block text-sm font-medium text-navy">From</label>
        <input id="from" type="date" name="from" value="{{ $p['from']->toDateString() }}" data-range-date
            class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
    </div>
    <div>
        <label for="to" class="block text-sm font-medium text-navy">To</label>
        <input id="to" type="date" name="to" value="{{ $p['to']->toDateString() }}" data-range-date
            class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
    </div>
    <div>
        <label for="group" class="block text-sm font-medium text-navy">Group by</label>
        <select id="group" name="group"
            class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
            <option value="" @selected(!request()->filled('group'))>Automatic ({{ ucfirst($p['group']) }})</option>
            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
                <option value="{{ $key }}" @selected(request('group') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn-primary !py-2">Apply</button>
    <a href="{{ $exportUrl }}" class="btn border border-navy/20 !py-2 text-navy">Export CSV</a>
</form>

@if ($errors->any())
    <p role="alert" class="mt-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</p>
@endif

<p class="mt-3 text-sm">Showing <strong>{{ $p['from']->format('d M Y') }}</strong> to
    <strong>{{ $p['to']->format('d M Y') }}</strong> ({{ $p['days'] }} {{ $p['days'] === 1 ? 'day' : 'days' }}),
    compared with the {{ $p['days'] }} {{ $p['days'] === 1 ? 'day' : 'days' }} before.</p>
