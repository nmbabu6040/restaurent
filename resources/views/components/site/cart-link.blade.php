@if (config('restaurant.ordering_enabled'))
    @php $count = app(\App\Services\CartService::class)->count(); @endphp
    <a href="{{ route('cart.show') }}" class="relative rounded-full p-2 text-white hover:bg-white/10"
        aria-label="{{ __('orders.cart') }}">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3 3h2l2.4 12.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm9 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
        </svg>
        <span data-cart-count
            class="absolute -right-1 -top-1 min-w-5 rounded-full bg-terracotta-dark px-1.5 text-center text-xs font-semibold text-white {{ $count ? '' : 'hidden' }}">{{ $count }}</span>
    </a>
@endif
