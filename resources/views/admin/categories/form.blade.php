@php $editing = $category->exists; @endphp

<x-admin.layout :title="$editing ? 'Edit category' : 'New category'">
    <form method="POST"
        action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="name_en" label="Name (English)" :required="true" :value="$category->name_en" maxlength="100" />
            <x-form.field name="name_bn" label="Name (বাংলা)" :value="$category->name_bn" maxlength="100" />
            <x-form.textarea name="description_en" label="Description (English)" :value="$category->description_en" rows="3"
                maxlength="500" />
            <x-form.textarea name="description_bn" label="Description (বাংলা)" :value="$category->description_bn" rows="3"
                maxlength="500" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="slug" label="URL slug (auto from English name if empty)" :value="$category->slug"
                maxlength="120" />
            <x-form.field name="sort_order" type="number" label="Sort order" :value="$category->sort_order" min="0"
                max="65535" />
        </div>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="accent-navy" @checked(old('is_active', $category->is_active))>
            Visible on the website
        </label>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.categories.index') }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
