@props(['url', 'title'])

@php
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $networks = [
        'Facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
        'WhatsApp' => "https://wa.me/?text={$t}%20{$u}",
        'X' => "https://twitter.com/intent/tweet?text={$t}&url={$u}",
        'Telegram' => "https://t.me/share/url?url={$u}&text={$t}",
        'LinkedIn' => "https://www.linkedin.com/sharing/share-offsite/?url={$u}",
    ];
@endphp

<div class="mt-6" role="group" aria-labelledby="share-title">
    <p id="share-title" class="text-sm font-medium text-navy">{{ __('site.share.title') }}</p>
    <ul class="mt-2 flex flex-wrap gap-2 text-sm">
        @foreach ($networks as $name => $href)
            <li>
                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="chip inline-block"
                    aria-label="{{ __('site.share.on', ['network' => $name]) }}">{{ $name }}</a>
            </li>
        @endforeach
        <li>
            <button type="button" class="chip" data-copy-link data-url="{{ $url }}"
                data-done="{{ __('site.share.copied') }}">{{ __('site.share.copy') }}</button>
        </li>
        <li class="hidden" data-native-share-wrap>
            <button type="button" class="chip" data-native-share data-url="{{ $url }}"
                data-title="{{ $title }}">{{ __('site.share.more') }}</button>
        </li>
    </ul>
</div>
