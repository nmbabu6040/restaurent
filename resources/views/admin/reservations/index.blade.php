<x-admin.layout title="Reservations">
    <form method="GET" action="{{ route('admin.reservations.index') }}" role="search"
        class="mt-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="date" class="block text-sm font-medium text-navy">Date</label>
            <input id="date" type="date" name="date" value="{{ request('date') }}"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-navy">Status</label>
            <select id="status" name="status"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (\App\Enums\ReservationStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ ucfirst($s->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q" class="block text-sm font-medium text-navy">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100"
                placeholder="Name, phone, email, reference"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn-primary !py-2">Filter</button>
        @if (request()->hasAny(['date', 'status', 'q']))
            <a href="{{ route('admin.reservations.index') }}"
                class="py-2 text-sm font-semibold text-navy hover:text-terracotta-dark">Reset</a>
        @endif
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Reference</th>
                    <th scope="col" class="p-4">Guest</th>
                    <th scope="col" class="p-4">When</th>
                    <th scope="col" class="p-4">Guests</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservations as $r)
                    <tr class="border-b border-navy/5">
                        <td class="p-4 font-mono text-xs">{{ $r->reference }}</td>
                        <td class="p-4"><span class="font-semibold text-navy">{{ $r->name }}</span><br><span
                                class="text-xs">{{ $r->phone }}</span></td>
                        <td class="p-4">{{ $r->reserved_at->format('D, d M Y') }}<br><span
                                class="text-xs">{{ $r->reserved_at->format('g:i A') }}</span></td>
                        <td class="p-4">{{ $r->party_size }}</td>
                        <td class="p-4"><span
                                class="rounded-full px-3 py-1 text-xs font-semibold {{ $r->status->badge() }}">{{ ucfirst($r->status->value) }}</span>
                        </td>
                        <td class="p-4 text-right"><a href="{{ route('admin.reservations.show', $r) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center">No reservations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $reservations->links() }}</div>
</x-admin.layout>
