<x-layouts.app :title="__('orders.track.title')" :noindex="true">
    <section class="mx-auto max-w-md px-6 py-14">
        <h1 class="text-center text-4xl font-bold">{{ __('orders.track.title') }}</h1>
        <p class="mt-3 text-center">{{ __('orders.track.subtitle') }}</p>

        <form method="POST" action="{{ route('orders.lookup') }}" class="card mt-8 space-y-5 p-6">
            @csrf
            <x-form.field name="reference" :label="__('orders.track.reference')" :required="true" maxlength="20" placeholder="MO-XXXXXXXX" />
            <x-form.field name="phone" type="tel" :label="__('orders.track.phone')" :required="true" maxlength="20"
                autocomplete="tel" />
            <button type="submit" class="btn-primary w-full">{{ __('orders.track.submit') }}</button>
        </form>
    </section>
</x-layouts.app>
