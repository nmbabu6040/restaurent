@php
    $statusButtons = [
        'approved' => ['Approve', 'btn-primary'],
        'hidden' => ['Hide', 'btn border border-navy/30 text-navy'],
        'pending' => ['Back to waiting', 'btn border border-navy/30 text-navy'],
    ];
@endphp

<x-admin.layout title="Review">
    <div class="card mt-6 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm"><a href="{{ route('admin.reviews.index') }}"
                    class="font-semibold text-navy hover:text-terracotta-dark">&larr; All reviews</a></p>
            <span class="rounded-full bg-navy/10 px-3 py-1 text-xs font-semibold">{{ ucfirst($review->status) }}</span>
        </div>

        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-semibold text-navy">Dish</dt>
                <dd>{{ $review->item?->name_en ?? '(deleted dish)' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Reviewer</dt>
                <dd>{{ $review->name }}@if ($review->is_verified)
                        <span
                            class="ml-1 rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-semibold text-white">Verified
                            buyer</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Rating</dt>
                <dd class="text-amber-700">
                    {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }} <span
                        class="text-charcoal">({{ $review->rating }}/5)</span></dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Posted</dt>
                <dd>{{ $review->created_at->format('d M Y, g:i A') }} &middot; {{ strtoupper($review->locale) }}</dd>
            </div>
            @if ($review->order)
                <div>
                    <dt class="font-semibold text-navy">Order</dt>
                    <dd><a href="{{ route('admin.orders.show', $review->order) }}"
                            class="hover:text-terracotta-dark">{{ $review->order->reference }}</a></dd>
                </div>
            @endif
            <div>
                <dt class="font-semibold text-navy">IP address</dt>
                <dd>{{ $review->ip_address ?: '—' }}</dd>
            </div>
        </dl>

        <p class="mt-5 whitespace-pre-line rounded-lg bg-warm p-4 text-sm">
            {{ $review->comment ?: '(no written review)' }}</p>

        <div class="mt-5 flex flex-wrap gap-3">
            @foreach ($statusButtons as $value => [$label, $class])
                @if ($review->status !== $value)
                    <form method="POST" action="{{ route('admin.reviews.status', $review) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $value }}">
                        <button type="submit" class="{{ $class }}">{{ $label }}</button>
                    </form>
                @endif
            @endforeach
            @can('manage-content')
                <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}"
                    data-confirm="Delete this review and its replies for good?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn border border-red-700 text-red-700 hover:bg-red-50">Delete</button>
                </form>
            @endcan
        </div>

        <h2 class="mt-8 text-xl font-semibold">Replies</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($review->replies as $reply)
                <li class="rounded-lg border border-navy/10 p-4 text-sm">
                    <p class="font-semibold text-navy">
                        {{ $reply->name }}
                        @if ($reply->is_staff)
                            <span
                                class="ml-1 rounded-full bg-navy px-2 py-0.5 text-xs font-semibold text-white">Restaurant</span>
                        @endif
                        <span class="ml-1 text-xs font-normal">{{ $reply->created_at->format('d M Y, g:i A') }}
                            &middot; {{ ucfirst($reply->status) }}@if ($reply->ip_address)
                                &middot; {{ $reply->ip_address }}
                            @endif
                        </span>
                    </p>
                    <p class="mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach (['approved' => 'Approve', 'hidden' => 'Hide'] as $value => $label)
                            @if ($reply->status !== $value)
                                <form method="POST" action="{{ route('admin.review-replies.status', $reply) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $value }}">
                                    <button type="submit"
                                        class="font-semibold text-navy hover:text-terracotta-dark">{{ $label }}</button>
                                </form>
                            @endif
                        @endforeach
                        @can('manage-content')
                            <form method="POST" action="{{ route('admin.review-replies.destroy', $reply) }}"
                                data-confirm="Delete this reply?">
                                @csrf @method('DELETE')
                                <button type="submit" class="font-semibold text-red-700 hover:underline">Delete</button>
                            </form>
                        @endcan
                    </div>
                </li>
                @empty
                    <li class="text-sm">No replies yet.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('admin.reviews.reply', $review) }}" class="mt-6 space-y-3">
                @csrf
                <x-form.textarea name="body" label="Reply as the restaurant (shown publicly)" :required="true"
                    rows="3" maxlength="600" />
                <button type="submit" class="btn-accent">Post reply</button>
                @if ($review->status !== 'approved')
                    <p class="text-xs">This review is not live, so the reply will show only after you approve the review.
                    </p>
                @endif
            </form>
        </div>
    </x-admin.layout>
