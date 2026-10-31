@php
    $brand = setting('name', config('app.name'));
    $logo = setting_image('logo_footer') ?: setting_image('logo_header');
    $authorName = setting('author_name');
    $authorUrl = setting('author_url');
@endphp

<x-site.section name="footer" as="footer">
    <div class="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-2 lg:grid-cols-4">
        <div>
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $brand }}" height="48" loading="lazy" class="h-12 w-auto">
            @else
                <p class="font-heading text-2xl font-bold">{{ $brand }}</p>
            @endif
            <p class="mt-3 text-sm opacity-80">{{ setting('tagline') }}</p>
            <div class="mt-4 flex gap-4 text-sm">
                <a href="{{ setting('facebook', '#') }}" class="hover:text-(color:--accent)" rel="noopener">Facebook</a>
                <a href="{{ setting('instagram', '#') }}" class="hover:text-(color:--accent)"
                    rel="noopener">Instagram</a>
            </div>
        </div>

        <div>
            <h2 class="font-heading text-lg">{{ __('site.footer.contact') }}</h2>
            <ul class="mt-3 space-y-2 text-sm opacity-80">
                <li>{{ setting('address') }}</li>
                <li><a href="tel:{{ preg_replace('/[^+\d]/', '', setting('phone', '')) }}"
                        class="hover:underline">{{ setting('phone') }}</a></li>
                <li><a href="mailto:{{ setting('email') }}" class="hover:underline">{{ setting('email') }}</a></li>
            </ul>
        </div>

        <div>
            <h2 class="font-heading text-lg">{{ __('site.footer.hours') }}</h2>
            <p class="mt-3 text-sm opacity-80">{!! nl2br(e(setting('hours', __('site.footer.hours_placeholder')))) !!}</p>
        </div>

        <div>
            <h2 class="font-heading text-lg">{{ __('forms.newsletter.title') }}</h2>
            <p class="mt-3 text-sm opacity-80">{{ __('forms.newsletter.text') }}</p>
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
                <p id="email-error-footer" data-error-for="email"
                    class="mt-1 text-sm font-semibold text-(color:--accent)"></p>
            </form>
        </div>
    </div>

    <div class="border-t border-current/10 px-6 py-5 text-center text-xs opacity-80">
        <nav aria-label="Footer" class="mb-3 flex flex-wrap justify-center gap-x-5 gap-y-2">
            <a href="{{ route('offers') }}" class="hover:underline">{{ __('pages.links.offers') }}</a>
            <a href="{{ route('testimonials') }}" class="hover:underline">{{ __('pages.links.testimonials') }}</a>
            <a href="{{ route('faq') }}" class="hover:underline">{{ __('pages.links.faq') }}</a>
            <a href="{{ route('orders.track') }}" class="hover:underline">{{ __('orders.track_link') }}</a>
            <a href="{{ route('privacy') }}" class="hover:underline">{{ __('pages.links.privacy') }}</a>
            <a href="{{ route('terms') }}" class="hover:underline">{{ __('pages.links.terms') }}</a>

            @foreach (\App\Models\Page::menu('footer') as $p)
                <a href="{{ route('pages.show', $p->slug) }}" class="hover:underline">{{ $p->tr('title') }}</a>
            @endforeach
        </nav>
        &copy; {{ date('Y') }} {{ $brand }}. {{ __('site.footer.rights') }}
        @if ($authorName)
            &middot; {{ __('site.credit') }}
            @if ($authorUrl)
                <a href="{{ $authorUrl }}" target="_blank" rel="noopener noreferrer"
                    class="font-semibold hover:underline">{{ $authorName }}</a>
            @else
                <span class="font-semibold">{{ $authorName }}</span>
            @endif
        @endif
    </div>
</x-site.section>
