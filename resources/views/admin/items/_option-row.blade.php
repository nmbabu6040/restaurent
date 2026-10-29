@php
    $row = $row ?? [];
    $isSize = $field === 'variations';
    $active = $row['is_active'] ?? true;
    $err =
        $errors->first("{$field}.{$i}.name_en") ?:
        ($errors->first("{$field}.{$i}.price") ?:
        $errors->first("{$field}.{$i}.cost_price"));
    $inputClass = 'rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta';
@endphp

<div data-option-row class="rounded-lg border border-navy/10 p-3">
    <div class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="{{ $field }}[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
        <input type="text" name="{{ $field }}[{{ $i }}][name_en]" value="{{ $row['name_en'] ?? '' }}"
            maxlength="100" placeholder="Name (English)" aria-label="Name (English)"
            class="{{ $inputClass }} min-w-40 flex-1">
        <input type="text" name="{{ $field }}[{{ $i }}][name_bn]"
            value="{{ $row['name_bn'] ?? '' }}" maxlength="100" placeholder="Name (বাংলা)" aria-label="Name (বাংলা)"
            class="{{ $inputClass }} min-w-40 flex-1">
        <input type="number" name="{{ $field }}[{{ $i }}][price]"
            value="{{ $row['price'] ?? '' }}" step="0.01" min="0" placeholder="Price"
            aria-label="{{ $priceLabel }}" class="{{ $inputClass }} w-28">
        @if ($isSize)
            <input type="number" name="{{ $field }}[{{ $i }}][portion_factor]"
                value="{{ $row['portion_factor'] ?? ($row ? '' : '1') }}" step="0.01" min="0.01"
                placeholder="Portion x" title="Portion factor: 1 = full recipe, 0.6 = 60% of the recipe"
                aria-label="Portion factor" class="{{ $inputClass }} w-24">
        @endif
        <input type="number" name="{{ $field }}[{{ $i }}][cost_price]"
            value="{{ $row['cost_price'] ?? '' }}" step="0.01" min="0" placeholder="Cost"
            title="Your cost for this {{ $isSize ? 'size' : 'extra' }}" aria-label="Cost price"
            class="{{ $inputClass }} w-24">
        <input type="hidden" name="{{ $field }}[{{ $i }}][is_active]" value="0">
        <label class="flex items-center gap-1 text-sm">
            <input type="checkbox" name="{{ $field }}[{{ $i }}][is_active]" value="1"
                class="accent-navy" @checked($active)> Active
        </label>
        <button type="button" data-remove-option
            class="text-sm font-semibold text-red-700 hover:underline">Remove</button>
    </div>
    @if ($err)
        <p class="mt-1 text-sm text-red-700">{{ $err }}</p>
    @endif
</div>
