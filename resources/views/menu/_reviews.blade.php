@php
    $count = (int) $item->reviews_count;
    $avg = (float) $item->rating_avg;
    $formAttrs =
        'data-ajax-form data-msg-error="' .
        e(__('forms.common.error')) .
        '" data-msg-throttled="' .
        e(__('forms.common.throttled')) .
        '"';
@endphp

<section id="reviews" class="mt-16" aria-labelledby="reviews-title">
    <h2 id="reviews-title" class="text-2xl font-bold">{{ __('reviews.title') }}</h2>

    @if ($count > 0)
        <div class="card mt-4 flex flex-wrap items-center gap-8 p-6">
            <div class="text-center">
                <p class="font-heading text-5xl font-bold text-navy">{{ number_format($avg, 1) }}</p>
                <p class="mt-1 text-xl"><x-site.stars :rating="$avg" /></p>
                <p class="text-sm">{{ trans_choice('reviews.summary', $count, ['count' => $count]) }}</p>
            </div>
            <ul class="min-w-48 flex-1 space-y-1 text-sm">
                @for ($star = 5; $star >= 1; $star--)
                    @php
                        $n = (int) ($distribution[$star] ?? 0);
                        $pct = $count ? round(($n / $count) * 100) : 0;
                    @endphp
                    <li class="flex items-center gap-2">
                        <span
                            class="w-14 shrink-0">{{ trans_choice('reviews.rating_n', $star, ['count' => $star]) }}</span>
                        <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-navy/10" aria-hidden="true"><span
                                class="block h-full rounded-full bg-amber-700"
                                style="width: {{ $pct }}%"></span></span>
                        <span class="w-8 shrink-0 text-right">{{ $n }}</span>
                    </li>
                @endfor
            </ul>
        </div>
    @else
        <p class="mt-3">{{ __('reviews.none') }}</p>
    @endif

    <div class="mt-6 space-y-5">
        @foreach ($reviews as $review)
            <article id="review-{{ $review->id }}" class="card scroll-mt-24 p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-navy">
                        {{ $review->name }}
                        @if ($review->is_verified)
                            <span
                                class="ml-1 rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-semibold text-white">{{ __('reviews.verified') }}</span>
                        @endif
                    </p>
                    <p class="text-xs">{{ $review->created_at->translatedFormat('d M Y') }}</p>
                </div>
                <p class="mt-1 text-lg"><x-site.stars :rating="$review->rating" /></p>
                @if ($review->comment)
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $review->comment }}</p>
                @endif

                @foreach ($review->approvedReplies as $reply)
                    <div
                        class="mt-3 rounded-lg {{ $reply->is_staff ? 'bg-warm' : 'border border-navy/10' }} p-3 text-sm">
                        <p class="font-semibold text-navy">
                            {{ $reply->name }}
                            @if ($reply->is_staff)
                                <span
                                    class="ml-1 rounded-full bg-navy px-2 py-0.5 text-xs font-semibold text-white">{{ __('reviews.restaurant') }}</span>
                            @endif
                            <span
                                class="ml-1 text-xs font-normal">{{ $reply->created_at->translatedFormat('d M Y') }}</span>
                        </p>
                        <p class="mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                    </div>
                @endforeach

                <div class="mt-3 flex flex-wrap items-start gap-x-6 gap-y-2">
                    <details class="min-w-56 flex-1">
                        <summary class="cursor-pointer text-sm font-semibold text-navy hover:text-terracotta-dark">
                            {{ __('reviews.reply') }}</summary>
                        <form method="POST" action="{{ route('reviews.reply', $review) }}" {!! $formAttrs !!}
                            class="mt-3 space-y-3">
                            @csrf
                            <div>
                                <label for="reply-name-{{ $review->id }}"
                                    class="sr-only">{{ __('reviews.reply_name') }}</label>
                                <input id="reply-name-{{ $review->id }}" name="name" required maxlength="100"
                                    placeholder="{{ __('reviews.reply_name') }}" autocomplete="name"
                                    class="w-full rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta">
                                <p data-error-for="name" class="text-sm text-red-700"></p>
                            </div>
                            <div>
                                <label for="reply-body-{{ $review->id }}"
                                    class="sr-only">{{ __('reviews.reply_body') }}</label>
                                <textarea id="reply-body-{{ $review->id }}" name="body" rows="2" required minlength="2" maxlength="600"
                                    placeholder="{{ __('reviews.reply_body') }}"
                                    class="w-full rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta"></textarea>
                                <p data-error-for="body" class="text-sm text-red-700"></p>
                            </div>
                            <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1"
                                    autocomplete="off"></div>
                            <button type="submit"
                                class="btn-primary !px-4 !py-2">{{ __('reviews.reply_submit') }}</button>
                        </form>
                    </details>

                    <details>
                        <summary class="cursor-pointer text-sm font-semibold text-navy hover:text-terracotta-dark">
                            {{ __('reviews.share') }}</summary>
                        <x-site.share :url="route('menu.show', $item->slug) . '#review-' . $review->id" :title="__('reviews.review_of', ['dish' => $item->name, 'name' => $review->name])" :label="__('reviews.share')" :compact="true" />
                    </details>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-6">{{ $reviews->links() }}</div>

    @if ($alreadyReviewed)
        <p class="card mt-8 p-5 text-sm font-semibold text-navy">{{ __('reviews.already_reviewed') }}</p>
    @else
        <form id="write-review" method="POST" action="{{ route('reviews.store', $item->slug) }}"
            {!! $formAttrs !!} class="card mt-8 scroll-mt-24 space-y-5 p-6">
            @csrf
            <h3 class="text-xl font-semibold">{{ __('reviews.write') }}</h3>

            @if ($orderForReview)
                <input type="hidden" name="order_token" value="{{ $orderForReview->token }}">
                <p class="rounded-lg bg-warm p-3 text-sm">{{ __('reviews.verified_note') }}</p>
            @endif
            <p data-error-for="order_token" role="alert" class="text-sm font-semibold text-red-700"></p>

            <fieldset>
                <legend class="text-sm font-medium text-navy">{{ __('reviews.your_rating') }}<span aria-hidden="true"
                        class="text-terracotta-dark"> *</span></legend>
                <div class="star-rating mt-1">
                    @for ($i = 5; $i >= 1; $i--)
                        <input type="radio" id="rating-{{ $i }}" name="rating"
                            value="{{ $i }}" required>
                        <label for="rating-{{ $i }}">
                            <span class="sr-only">{{ trans_choice('reviews.rating_n', $i, ['count' => $i]) }}</span>
                            <span aria-hidden="true">★</span>
                        </label>
                    @endfor
                </div>
                <p data-error-for="rating" class="mt-1 text-sm text-red-700"></p>
            </fieldset>

            <x-form.field name="name" :label="__('reviews.name')" :required="true" :value="$orderForReview ? \Illuminate\Support\Str::before($orderForReview->name, ' ') : null" maxlength="100"
                autocomplete="name" />
            <x-form.textarea name="comment" :label="__('reviews.comment')" rows="3" maxlength="1000" />

            <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1"
                    autocomplete="off"></div>
            <button type="submit" class="btn-accent">{{ __('reviews.submit') }}</button>
        </form>
    @endif
</section>
