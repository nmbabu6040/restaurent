<x-layouts.app :title="__('orders.cart')" :noindex="true">
    <section class="mx-auto max-w-6xl px-6 py-12">
        <h1 class="text-4xl font-bold">{{ __('orders.cart') }}</h1>
        <div id="cart-root" aria-live="polite" class="mt-8">
            @include('cart._content')
        </div>
    </section>
</x-layouts.app>
