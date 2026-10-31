@php $editing = $page->exists; @endphp

<x-admin.layout :title="$editing ? 'Edit page' : 'New page'">
    <form method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="title_en" label="Title (English)" :required="true" :value="$page->title_en" maxlength="150" />
            <x-form.field name="title_bn" label="Title (বাংলা)" :value="$page->title_bn" maxlength="150" />
            @if ($page->is_system)
                <p class="text-sm md:col-span-2">Address: <span class="font-mono">/{{ $page->slug }}</span> (fixed)
                </p>
            @else
                <x-form.field name="slug" label="Address (auto from the English title if empty): /page/..."
                    :value="$page->slug" maxlength="120" />
            @endif
        </div>

        <p class="rounded-lg bg-warm p-3 text-sm">
            Write with <strong>Markdown</strong>: <code>## Heading</code>, <code>**bold**</code>, <code>*italic*</code>,
            <code>- list item</code>, <code>1. numbered</code>, <code>[link text](https://example.com)</code>. Leave a
            blank line between paragraphs. HTML is not allowed.
        </p>
        <div class="grid gap-5 md:grid-cols-2">
            <x-form.textarea name="body_en" label="Content (English)" :value="$page->body_en" rows="16" />
            <x-form.textarea name="body_bn" label="Content (বাংলা, shows when the visitor picks Bangla)"
                :value="$page->body_bn" rows="16" />
        </div>

        <details class="rounded-xl border border-navy/10 p-4">
            <summary class="cursor-pointer font-heading text-lg text-navy">Search engine text (optional)</summary>
            <div class="mt-3 grid gap-5 md:grid-cols-2">
                <x-form.field name="meta_title_en" label="SEO title (English)" :value="$page->meta_title_en" maxlength="160" />
                <x-form.field name="meta_title_bn" label="SEO title (বাংলা)" :value="$page->meta_title_bn" maxlength="160" />
                <x-form.textarea name="meta_description_en" label="SEO description (English, about 150 characters)"
                    :value="$page->meta_description_en" rows="2" maxlength="300" />
                <x-form.textarea name="meta_description_bn" label="SEO description (বাংলা)" :value="$page->meta_description_bn"
                    rows="2" maxlength="300" />
            </div>
        </details>

        <div class="flex flex-wrap gap-6 text-sm">
            @foreach (['show_in_header' => 'Link in the top menu', 'show_in_footer' => 'Link in the footer', 'is_active' => 'Visible on the website'] as $field => $label)
                <input type="hidden" name="{{ $field }}" value="0">
                <label class="flex items-center gap-2"><input type="checkbox" name="{{ $field }}" value="1"
                        class="accent-navy" @checked(old($field, $page->{$field}))> {{ $label }}</label>
            @endforeach
        </div>
        <x-form.field name="sort_order" type="number" label="Order in the menu (small number first)" :value="$page->sort_order"
            min="0" max="65535" />

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.pages.index') }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
