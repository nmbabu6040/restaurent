@props(['url', 'title', 'label' => null, 'compact' => false])

@php
    $uid = 'share-' . substr(md5($url), 0, 8);
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $networks = [
        'Facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
        'WhatsApp' => "https://wa.me/?text={$t}%20{$u}",
        'X' => "https://twitter.com/intent/tweet?text={$t}&url={$u}",
        'Telegram' => "https://t.me/share/url?url={$u}&text={$t}",
        'LinkedIn' => "https://www.linkedin.com/sharing/share-offsite/?url={$u}",
    ];
    $chip = $compact ? 'chip !px-3 !py-1 text-xs' : 'chip';
@endphp

<div class="{{ $compact ? 'mt-2' : 'mt-6' }}" role="group" aria-labelledby="{{ $uid }}">
    <p id="{{ $uid }}"
        class="{{ $compact ? 'text-xs font-medium text-navy' : 'text-sm font-medium text-navy' }}">
        {{ $label ?? __('site.share.title') }}</p>
    <ul class="mt-2 flex flex-wrap gap-2 text-sm">
        @foreach ($networks as $name => $href)
            <li>
                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer"
                    class="{{ $chip }} inline-block"
                    aria-label="{{ __('site.share.on', ['network' => $name]) }}">{{ $name }}</a>
            </li>
        @endforeach
        <li>
            <button type="button" class="{{ $chip }}" data-copy-link data-url="{{ $url }}"
                data-done="{{ __('site.share.copied') }}">{{ __('site.share.copy') }}</button>
        </li>
        <li class="hidden" data-native-share-wrap>
            <button type="button" class="{{ $chip }}" data-native-share data-url="{{ $url }}"
                data-title="{{ $title }}">{{ __('site.share.more') }}</button>
        </li>
    </ul>
</div>
