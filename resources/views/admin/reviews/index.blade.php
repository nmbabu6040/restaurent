<x-admin.layout title="Reviews">
    <div class="mt-6 flex flex-wrap gap-2 text-sm" role="group" aria-label="Filter by status">
        @foreach (['pending' => 'Waiting', 'approved' => 'Live', 'hidden' => 'Hidden', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.reviews.index', array_merge(request()->except('page'), ['status' => $key])) }}"
                @if ($status === $key) aria-current="true" @endif class="chip">
                {{ $label }}@if ($key !== 'all')
                    ({{ $counts[$key] ?? 0 }})
                @else
                    ({{ array_sum($counts) }})
                @endif
            </a>
        @endforeach
    </div>

    @if ($pendingReplies > 0)
        <p class="mt-3 text-sm font-semibold text-terracotta-dark">{{ $pendingReplies }} visitor
            {{ $pendingReplies === 1 ? 'reply is' : 'replies are' }} waiting for approval (open a review to see them).
        </p>
    @endif

    <form method="GET" action="{{ route('admin.reviews.index') }}" role="search"
        class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div>
            <label for="rating" class="block text-sm font-medium text-navy">Stars</label>
            <select id="rating" name="rating"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
                <option value="">Any</option>
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected((int) request('rating') === $i)>{{ $i }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label for="q" class="block text-sm font-medium text-navy">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100"
                placeholder="Dish, name or text"
                class="mt-1 rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm">
        </div>
        <button type="submit" class="btn-primary !py-2">Filter</button>
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">Dish</th>
                    <th scope="col" class="p-4">Reviewer</th>
                    <th scope="col" class="p-4">Stars</th>
                    <th scope="col" class="p-4">Review</th>
                    <th scope="col" class="p-4">Status</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reviews as $r)
                    <tr class="border-b border-navy/5">
                        <td class="p-4 font-semibold text-navy">{{ $r->item?->name_en ?? '(deleted dish)' }}<br><span
                                class="text-xs font-normal">{{ $r->created_at->format('d M Y') }}</span></td>
                        <td class="p-4">{{ $r->name }}@if ($r->is_verified)
                                <br><span
                                    class="rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-semibold text-white">Verified
                                    buyer</span>
                            @endif
                        </td>
                        <td class="p-4 text-amber-700">
                            {{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}<span
                                class="sr-only">{{ $r->rating }} of 5</span></td>
                        <td class="p-4">{{ \Illuminate\Support\Str::limit((string) $r->comment, 80) ?: '—' }}</td>
                        <td class="p-4">
                            {{ ucfirst($r->status) }}
                            @if ($r->pending_replies_count)
                                <span
                                    class="ml-1 rounded-full bg-terracotta-dark px-2 py-0.5 text-xs font-semibold text-white">{{ $r->pending_replies_count }}
                                    reply</span>
                            @endif
                        </td>
                        <td class="p-4 text-right"><a href="{{ route('admin.reviews.show', $r) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">Open</a></td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center">No reviews here.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $reviews->links() }}</div>
    </x-admin.layout>
