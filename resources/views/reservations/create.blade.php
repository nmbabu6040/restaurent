<x-layouts.app :title="__('forms.reservation.title')" :description="__('forms.reservation.subtitle')">
    <section class="mx-auto max-w-2xl px-6 py-14">
        <header class="text-center">
            <h1 class="text-4xl font-bold">{{ __('forms.reservation.title') }}</h1>
            <p class="mt-3">{{ __('forms.reservation.subtitle') }}</p>
        </header>

        <form id="reservation-form" method="POST" action="{{ route('reservations.store') }}" data-ajax-form
            data-availability-url="{{ route('reservations.availability') }}"
            data-msg-choose="{{ __('forms.reservation.choose_time') }}" data-msg-full="{{ __('forms.reservation.full') }}"
            data-msg-none="{{ __('forms.reservation.errors.none_available') }}"
            data-msg-load-failed="{{ __('forms.reservation.errors.load_failed') }}"
            data-msg-error="{{ __('forms.common.error') }}" data-msg-throttled="{{ __('forms.common.throttled') }}"
            class="card mt-10 space-y-5 p-6 sm:p-8">
            @csrf

            <x-form.field name="name" :label="__('forms.reservation.name')" :required="true" maxlength="100" autocomplete="name" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="email" type="email" :label="__('forms.reservation.email')" :required="true" maxlength="150"
                    autocomplete="email" />
                <x-form.field name="phone" type="tel" :label="__('forms.reservation.phone')" :required="true" maxlength="20"
                    autocomplete="tel" />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.field name="date" type="date" :label="__('forms.reservation.date')" :required="true"
                    min="{{ $minDate }}" max="{{ $maxDate }}" />

                <div>
                    <label for="party_size"
                        class="block text-sm font-medium text-navy">{{ __('forms.reservation.party_size') }}<span
                            aria-hidden="true" class="text-terracotta-dark"> *</span></label>
                    <select id="party_size" name="party_size" required aria-describedby="party_size-error"
                        class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                        @for ($i = 1; $i <= $maxParty; $i++)
                            <option value="{{ $i }}" @selected((int) old('party_size', 2) === $i)>{{ $i }}
                            </option>
                        @endfor
                    </select>
                    <p id="party_size-error" data-error-for="party_size" class="mt-1 text-sm text-red-700">
                        {{ $errors->first('party_size') }}</p>
                </div>

                <div>
                    <label for="time"
                        class="block text-sm font-medium text-navy">{{ __('forms.reservation.time') }}<span
                            aria-hidden="true" class="text-terracotta-dark"> *</span></label>
                    <select id="time" name="time" required data-old="{{ old('time') }}"
                        aria-describedby="time-error"
                        class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                        <option value="">{{ __('forms.reservation.choose_time') }}</option>
                        @foreach ($times as $slot)
                            <option value="{{ $slot['time'] }}" @selected(old('time') === $slot['time'])>{{ $slot['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <p id="time-error" data-error-for="time" class="mt-1 text-sm text-red-700">
                        {{ $errors->first('time') }}</p>
                </div>
            </div>

            <x-form.textarea name="message" :label="__('forms.reservation.message')" maxlength="500" rows="3" />

            <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1"
                    autocomplete="off"></div>

            <button type="submit" class="btn-accent w-full">{{ __('forms.reservation.submit') }}</button>
        </form>
    </section>
</x-layouts.app>
