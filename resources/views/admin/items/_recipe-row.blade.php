@php
    $row = $row ?? [];
    $inputClass = 'rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta';
    $err = $errors->first("recipe.{$i}.ingredient_id") ?: $errors->first("recipe.{$i}.quantity");
@endphp

<div data-option-row class="rounded-lg border border-navy/10 p-3">
    <div class="flex flex-wrap items-center gap-3">
        <select name="recipe[{{ $i }}][ingredient_id]" aria-label="Ingredient"
            class="{{ $inputClass }} min-w-52 flex-1">
            <option value="">Choose an ingredient…</option>
            @foreach ($ingredients as $ingredient)
                <option value="{{ $ingredient->id }}" @selected((int) ($row['ingredient_id'] ?? 0) === $ingredient->id)>{{ $ingredient->name }}
                    ({{ $ingredient->unit }})</option>
            @endforeach
        </select>
        <input type="number" name="recipe[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}"
            step="0.001" min="0" placeholder="Per portion"
            aria-label="Quantity per portion, in the ingredient's unit" class="{{ $inputClass }} w-36">
        <button type="button" data-remove-option
            class="text-sm font-semibold text-red-700 hover:underline">Remove</button>
    </div>
    @if ($err)
        <p class="mt-1 text-sm text-red-700">{{ $err }}</p>
    @endif
</div>
