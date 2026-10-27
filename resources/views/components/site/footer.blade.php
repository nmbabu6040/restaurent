<footer class="bg-navy text-white/90">
    <div class="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="font-heading text-2xl font-bold text-white">{{ setting('name', config('app.name')) }}</p>
            <p class="mt-3 text-sm text-white/70">{{ setting('tagline') }}</p>
            <div class="mt-4 flex gap-4 text-sm">
                <a href="{{ setting('facebook', '#') }}" class="hover:text-terracotta" rel="noopener">Facebook</a>
                <a href="{{ setting('instagram', '#') }}" class="hover:text-terracotta" rel="noopener">Instagram</a>
            </div>
        </div>

        <div>
            <h2 class="font-heading text-lg text-white">{{ __('site.footer.contact') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-white/70">
                <li>{{ setting('address') }}</li>
                <li><a href="tel:{{ preg_replace('/[^+\d]/', '', setting('phone', '')) }}"
                        class="hover:text-white">{{ setting('phone') }}</a></li>
                <li><a href="mailto:{{ setting('email') }}" class="hover:text-white">{{ setting('email') }}</a></li>
            </ul>
        </div>

        <div>
            <h2 class="font-heading text-lg text-white">{{ __('site.footer.hours') }}</h2>
            <p class="mt-3 text-sm text-white/70">{!! nl2br(e(setting('hours', __('site.footer.hours_placeholder')))) !!}</p>
        </div>

        <div>
            <h2 class="font-heading text-lg text-white">{{ __('forms.newsletter.title') }}</h2>
            <p class="mt-3 text-sm text-white/70">{{ __('forms.newsletter.text') }}</p>
            <form method="POST" action="{{ route('newsletter.store') }}" data-ajax-form
                data-msg-error="{{ __('forms.common.error') }}"
                data-msg-throttled="{{ __('forms.common.throttled') }}" class="mt-3">
                @csrf
                <div class="flex gap-2">
                    <label for="newsletter-email" class="sr-only">{{ __('forms.newsletter.email') }}</label>
                    <input id="newsletter-email" type="email" name="email" required maxlength="150"
                        autocomplete="email" placeholder="{{ __('forms.newsletter.email') }}"
                        aria-describedby="email-error-footer"
                        class="w-full rounded-full border-0 bg-white px-4 py-2 text-sm text-charcoal">
                    <button type="submit"
                        class="btn-accent shrink-0 !px-4 !py-2">{{ __('forms.newsletter.subscribe') }}</button>
                </div>
                <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1"
                        autocomplete="off"></div>
                <p id="email-error-footer" data-error-for="email" class="mt-1 text-sm text-red-200"></p>
            </form>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-white/60">
        &copy; {{ date('Y') }} {{ setting('name', config('app.name')) }}. {{ __('site.footer.rights') }}
    </div>
</footer>
