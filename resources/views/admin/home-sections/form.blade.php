@php $editing = $section->exists; @endphp

<x-admin.layout :title="$editing ? 'Edit: ' . $section->label() : 'New custom section'">
    <form method="POST"
        action="{{ $editing ? route('admin.home-sections.update', $section) : route('admin.home-sections.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        @if ($editing && $section->key === 'hero')
            <p class="rounded-lg bg-warm p-3 text-sm">The hero headline and sub-heading come from Settings, the photo and
                overlay from Appearance & site. Here you can only hide or show it.</p>
        @else
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.field name="title_en" label="Heading (English)" :required="$section->is_custom" :value="$section->title_en"
                    maxlength="150" />
                <x-form.field name="title_bn" label="Heading (বাংলা)" :value="$section->title_bn" maxlength="150" />
                <x-form.field name="subtitle_en" label="Sub-heading (English)" :value="$section->subtitle_en" maxlength="300" />
                <x-form.field name="subtitle_bn" label="Sub-heading (বাংলা)" :value="$section->subtitle_bn" maxlength="300" />
            </div>
            @if ($section->is_custom || $section->key === 'intro')
                <p class="text-sm">Text uses Markdown (<code>**bold**</code>, <code>- list</code>,
                    <code>[link](https://…)</code>).</p>
                <div class="grid gap-5 md:grid-cols-2">
                    <x-form.textarea name="body_en" label="Text (English)" :value="$section->body_en" rows="6"
                        maxlength="5000" />
                    <x-form.textarea name="body_bn" label="Text (বাংলা)" :value="$section->body_bn" rows="6"
                        maxlength="5000" />
                </div>
            @endif
            @if ($section->is_custom)
                <div class="grid gap-5 md:grid-cols-2">
                    <x-form.field name="button_text_en" label="Button text (English, optional)" :value="$section->button_text_en"
                        maxlength="60" />
                    <x-form.field name="button_text_bn" label="Button text (বাংলা)" :value="$section->button_text_bn" maxlength="60" />
                    <x-form.field name="button_url" label="Button link (/menu or https://…)" :value="$section->button_url"
                        maxlength="255" />
                    <div>
                        <label for="bg_color" class="block text-sm font-medium text-navy">Background color
                            (optional)</label>
                        <input id="bg_color" type="color" name="bg_color"
                            value="{{ old('bg_color', $section->bg_color ?: '#FAF7F2') }}"
                            class="mt-1 h-10 w-16 rounded border border-navy/20 p-0.5">
                        <p class="mt-1 text-sm text-red-700">{{ $errors->first('bg_color') }}</p>
                    </div>
                </div>
            @endif
        @endif

        <input type="hidden" name="is_visible" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_visible" value="1"
                class="accent-navy" @checked(old('is_visible', $section->is_visible))> Shown on the home page</label>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.home-sections.index') }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
