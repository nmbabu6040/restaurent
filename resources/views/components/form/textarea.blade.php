@props(['name', 'label', 'required' => false, 'rows' => 4])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-navy">
        {{ $label }}@if ($required)
            <span aria-hidden="true" class="text-terracotta-dark"> *</span>
        @endif
    </label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
        aria-describedby="{{ $name }}-error" @if ($errors->has($name)) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta aria-[invalid=true]:border-red-700']) }}>{{ old($name) }}</textarea>
    <p id="{{ $name }}-error" data-error-for="{{ $name }}" class="mt-1 text-sm text-red-700">
        {{ $errors->first($name) }}</p>
</div>
