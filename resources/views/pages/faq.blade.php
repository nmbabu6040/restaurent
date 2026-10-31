<x-layouts.app :title="__('pages.faq.title')" :description="__('pages.faq.subtitle')">

    @push('head')
        <x-seo.json-ld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs
                ->map(
                    fn($f) => [
                        '@type' => 'Question',
                        'name' => $f->question_en,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer_en],
                    ],
                )
                ->values()
                ->all(),
        ]" />
    @endpush

    <x-site.page-header :title="__('pages.faq.title')" :subtitle="__('pages.faq.subtitle')" />

    <section class="mx-auto max-w-3xl px-6 py-12">
        @if ($faqs->isEmpty())
            <p class="text-center">{{ __('pages.faq.empty') }}</p>
        @else
            <div class="space-y-3">
                @foreach ($faqs as $faq)
                    <details class="card group p-5">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-4 font-heading text-lg text-navy">
                            {{ $faq->tr('question') }}
                            <span aria-hidden="true" class="text-terracotta-dark transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 text-sm">{{ $faq->tr('answer') }}</p>
                    </details>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
