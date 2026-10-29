@php
    $row = $row ?? [];
    $active = $row['is_active'] ?? true;
    $err = $errors->first("{$field}.{$i}.name_en") ?: $errors->first("{$field}.{$i}.price");
    $inputClass = 'w-full rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta';
@endphp

<div data-option-row class="rounded-lg border border-navy/10 p-3">
    <div class="grid gap-3 md:grid-cols-[1fr_1fr_9rem_auto]">
        <input type="hidden" name="{{ $field }}[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
        <input type="text" name="{{ $field }}[{{ $i }}][name_en]" value="{{ $row['name_en'] ?? '' }}"
            maxlength="100" placeholder="Name (English)" aria-label="Name (English)" class="{{ $inputClass }}">
        <input type="text" name="{{ $field }}[{{ $i }}][name_bn]"
            value="{{ $row['name_bn'] ?? '' }}" maxlength="100" placeholder="Name (বাংলা)" aria-label="Name (বাংলা)"
            class="{{ $inputClass }}">
        <input type="number" name="{{ $field }}[{{ $i }}][price]"
            value="{{ $row['price'] ?? '' }}" step="0.01" min="0" placeholder="Price"
            aria-label="{{ $priceLabel }}" class="{{ $inputClass }}">
        <div class="flex items-center gap-3 text-sm">
            <input type="hidden" name="{{ $field }}[{{ $i }}][is_active]" value="0">
            <label class="flex items-center gap-1">
                <input type="checkbox" name="{{ $field }}[{{ $i }}][is_active]" value="1"
                    class="accent-navy" @checked($active)> Active
            </label>
            <button type="button" data-remove-option class="font-semibold text-red-700 hover:underline">Remove</button>
        </div>
    </div>
    @if ($err)
        <p class="mt-1 text-sm text-red-700">{{ $err }}</p>
    @endif
</div>
