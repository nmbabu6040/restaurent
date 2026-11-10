@php
    $row = $row ?? [];
    $field = 'rounded-lg border border-navy/20 bg-surface px-3 py-2 text-sm focus:border-terracotta';
@endphp

<div data-pay-row class="rounded-lg border border-navy/10 p-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-navy">Method</label>
            <select name="payments[{{ $i }}][method]" data-pay-method class="{{ $field }}">
                @foreach (\App\Models\OrderPayment::METHODS as $value => $label)
                    <option value="{{ $value }}" @selected(($row['method'] ?? 'cash') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-navy">Amount for the bill</label>
            <input name="payments[{{ $i }}][amount]" data-pay-amount type="number" step="0.01"
                min="0.01" value="{{ $row['amount'] ?? '' }}" class="w-32 {{ $field }}">
        </div>
        <div data-tender-wrap>
            <label class="block text-xs font-medium text-navy">Cash given</label>
            <input name="payments[{{ $i }}][tendered]" data-pay-tendered type="number" step="0.01"
                min="0" value="{{ $row['tendered'] ?? '' }}" class="w-32 {{ $field }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-navy">Reference (optional)</label>
            <input name="payments[{{ $i }}][reference]" maxlength="60" placeholder="Card / TrxID"
                value="{{ $row['reference'] ?? '' }}" class="w-36 {{ $field }}">
        </div>
        <button type="button" data-pay-fill class="text-sm font-semibold text-navy hover:text-terracotta-dark">Fill
            rest</button>
        <button type="button" data-pay-remove
            class="text-sm font-semibold text-red-700 hover:underline">Remove</button>
    </div>
</div>
