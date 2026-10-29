@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-navy">
        {{ $label }}@if ($required)
            <span aria-hidden="true" class="text-terracotta-dark"> *</span>
        @endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @required($required) aria-describedby="{{ $name }}-error"
        @if ($errors->has($name)) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta aria-[invalid=true]:border-red-700']) }}>
    <p id="{{ $name }}-error" aria-live="polite" data-error-for="{{ $name }}"
        class="mt-1 text-sm text-red-700">
        {{ $errors->first($name) }}</p>
</div>
