@php
    $whatsapp = whatsapp_link();
    $backToTop = setting('back_to_top_enabled', '1') === '1';
@endphp

@if ($whatsapp || $backToTop)
    <div class="fixed bottom-4 right-4 z-40 flex flex-col items-center gap-3">
        @if ($backToTop)
            <div data-back-to-top-wrap class="hidden">
                <button type="button" aria-label="{{ __('site.back_to_top') }}"
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-[#172B4D] text-white shadow-lg ring-2 ring-white/70 transition hover:scale-105">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M5 12l7-7 7 7" />
                    </svg>
                </button>
            </div>
        @endif

        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ __('site.whatsapp') }}"
                class="flex h-14 w-14 items-center justify-center rounded-full bg-[#1FA855] text-white shadow-lg ring-2 ring-white/70 transition hover:scale-105">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
                </svg>
            </a>
        @endif
    </div>
@endif
