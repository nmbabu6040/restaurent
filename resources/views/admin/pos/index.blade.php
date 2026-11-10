<x-admin.layout title="POS">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3" data-pos-poll-url="{{ route('admin.pos.state') }}" data-signature="{{ $signature }}">
        @if ($shift)
            <p class="rounded-full bg-green-800 px-4 py-1.5 text-sm font-semibold text-white">Shift open since {{ $shift->opened_at->format('g:i A') }}</p>
        @else
            <p class="rounded-full bg-amber-700 px-4 py-1.5 text-sm font-semibold text-white">No shift open. Cash payments are not tracked until you open one.</p>
        @endif
        <a href="{{ route('admin.pos.shift') }}" class="btn border border-navy/30 !py-2 text-navy">Cash drawer</a>
    </div>

    <h2 class="mt-8 text-xl font-semibold">Tables</h2>
    @if ($tables->isEmpty())
        <p class="mt-3 text-sm">No tables yet. <a href="{{ route('admin.content.create', 'tables') }}" class="font-semibold text-navy underline">Add your tables</a>, or use Takeaway below.</p>
    @endif
    <ul class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($tables as $table)
            @php $bill = $byTable->get($table->id); @endphp
            <li>
                @if ($bill)
                    <a href="{{ route('admin.pos.show', $bill) }}" class="block rounded-2xl bg-linear-to-br from-amber-700 to-orange-700 p-4 text-white shadow-card transition hover:-translate-y-0.5">
                        <span class="block font-heading text-2xl font-bold">{{ $table->name }}</span>
                        <span class="block text-xs text-white/90">{{ $table->section }}</span>
                        <span class="mt-3 block text-lg font-semibold">{{ money($bill->total) }}</span>
                        <span class="block text-xs text-white/90">{{ $bill->items->sum('quantity') }} items &middot; {{ $bill->created_at->diffForHumans(null, true) }}</span>
                    </a>
                @else
                    <form method="POST" action="{{ route('admin.pos.store') }}">
                        @csrf
                        <input type="hidden" name="type" value="dine_in">
                        <input type="hidden" name="dining_table_id" value="{{ $table->id }}">
                        <button type="submit" class="block w-full rounded-2xl bg-linear-to-br from-emerald-700 to-teal-700 p-4 text-left text-white shadow-card transition hover:-translate-y-0.5">
                            <span class="block font-heading text-2xl font-bold">{{ $table->name }}</span>
                            <span class="block text-xs text-white/90">{{ $table->section }}</span>
                            <span class="mt-3 block text-sm">Free &middot; {{ $table->seats }} seats</span>
                            <span class="block text-xs text-white/90">Tap to open a bill</span>
                        </button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    <h2 class="mt-8 text-xl font-semibold">Takeaway</h2>
    <form method="POST" action="{{ route('admin.pos.store') }}" class="card mt-3 flex flex-wrap items-end gap-3 p-4">
        @csrf
        <input type="hidden" name="type" value="pickup">
        <div>
            <label for="tk-name" class="block text-sm font-medium text-navy">Name (optional)</label>
            <input id="tk-name" name="name" maxlength="100" class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <div>
            <label for="tk-phone" class="block text-sm font-medium text-navy">Phone (optional)</label>
            <input id="tk-phone" name="phone" type="tel" maxlength="20" class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn-accent !py-2">New takeaway bill</button>
    </form>

    @if ($takeaways->isNotEmpty())
        <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($takeaways as $bill)
                <li>
                    <a href="{{ route('admin.pos.show', $bill) }}" class="block rounded-2xl bg-linear-to-br from-indigo-600 to-violet-700 p-4 text-white shadow-card transition hover:-translate-y-0.5">
                        <span class="block font-semibold">{{ $bill->name }}</span>
                        <span class="block text-lg font-semibold">{{ money($bill->total) }}</span>
                        <span class="block text-xs text-white/90">{{ $bill->items->sum('quantity') }} items &middot; {{ $bill->created_at->diffForHumans(null, true) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-admin.layout>
