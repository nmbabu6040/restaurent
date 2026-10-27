@php
    $address = setting('address', '');
    $mapEmbed = setting('map_embed_url');
    $mapOk = $mapEmbed && str_starts_with($mapEmbed, 'https://www.google.com/maps/embed');
@endphp

<x-layouts.app :title="__('forms.contact.title')" :description="__('forms.contact.subtitle')">
    <section class="mx-auto max-w-6xl px-6 py-14">
        <header class="text-center">
            <h1 class="text-4xl font-bold">{{ __('forms.contact.title') }}</h1>
            <p class="mx-auto mt-3 max-w-xl">{{ __('forms.contact.subtitle') }}</p>
        </header>

        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            <div class="space-y-6">
                <div class="card p-6">
                    <h2 class="text-xl font-semibold">{{ __('forms.contact.find_us') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li>{{ $address }}</li>
                        <li><a href="tel:{{ preg_replace('/[^+\d]/', '', setting('phone', '')) }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">{{ setting('phone') }}</a>
                        </li>
                        <li><a href="mailto:{{ setting('email') }}"
                                class="font-semibold text-navy hover:text-terracotta-dark">{{ setting('email') }}</a>
                        </li>
                    </ul>
                    <p class="mt-4 text-sm">{!! nl2br(e(setting('hours', ''))) !!}</p>
                    <div class="mt-4 flex gap-4 text-sm">
                        <a href="{{ setting('facebook', '#') }}" rel="noopener"
                            class="font-semibold text-navy hover:text-terracotta-dark">Facebook</a>
                        <a href="{{ setting('instagram', '#') }}" rel="noopener"
                            class="font-semibold text-navy hover:text-terracotta-dark">Instagram</a>
                    </div>
                    @if ($address)
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}"
                            target="_blank" rel="noopener noreferrer"
                            class="btn-primary mt-5">{{ __('forms.contact.directions') }}</a>
                    @endif
                </div>

                @if ($mapOk)
                    <iframe src="{{ $mapEmbed }}" title="{{ __('forms.contact.find_us') }}" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade" class="h-72 w-full rounded-2xl border-0"></iframe>
                @endif
            </div>

            <form method="POST" action="{{ route('contact.store') }}" data-ajax-form
                data-msg-error="{{ __('forms.common.error') }}"
                data-msg-throttled="{{ __('forms.common.throttled') }}" class="card space-y-5 p-6 sm:p-8">
                @csrf
                <x-form.field name="name" :label="__('forms.contact.name')" :required="true" maxlength="100" autocomplete="name" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field name="email" type="email" :label="__('forms.contact.email')" :required="true" maxlength="150"
                        autocomplete="email" />
                    <x-form.field name="phone" type="tel" :label="__('forms.contact.phone')" maxlength="20" autocomplete="tel" />
                </div>
                <x-form.field name="subject" :label="__('forms.contact.subject')" maxlength="150" />
                <x-form.textarea name="message" :label="__('forms.contact.message')" :required="true" maxlength="2000" rows="5" />
                <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1"
                        autocomplete="off"></div>
                <button type="submit" class="btn-accent w-full">{{ __('forms.contact.submit') }}</button>
            </form>
        </div>
    </section>
</x-layouts.app>
