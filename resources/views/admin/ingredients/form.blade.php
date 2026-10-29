```blade
@php
    $editing = $ingredient->exists;
@endphp

<x-admin.layout :title="$editing ? 'Edit ingredient' : 'New ingredient'">
    <form method="POST"
        action="{{ $editing ? route('admin.ingredients.update', $ingredient) : route('admin.ingredients.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="name" label="Name" :required="true" :value="$ingredient->name" maxlength="120" />

            <div>
                <label for="unit" class="block text-sm font-medium text-navy">
                    Unit
                    <span aria-hidden="true" class="text-terracotta-dark">*</span>
                </label>

                <select id="unit" name="unit" required
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                    @foreach (\App\Models\Ingredient::UNITS as $value => $label)
                        <option value="{{ $value }}" @selected(old('unit', $ingredient->unit) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <p class="mt-1 text-sm text-red-700">
                    {{ $errors->first('unit') }}
                </p>
            </div>

            <x-form.field name="unit_cost" type="number" label="Cost per 1 unit (e.g. per kg)" :required="true"
                :value="qty($ingredient->unit_cost)" step="0.0001" min="0" />

            <x-form.field name="reorder_level" type="number" label="Reorder level (low-stock alert at or below)"
                :required="true" :value="qty($ingredient->reorder_level)" step="0.001" min="0" />

            @unless ($editing)
                <x-form.field name="opening_stock" type="number" label="Stock you have right now (optional)" step="0.001"
                    min="0" />
            @endunless

            <div>
                <label for="supplier_id" class="block text-sm font-medium text-navy">
                    Supplier (optional)
                </label>

                <select id="supplier_id" name="supplier_id"
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                    <option value="">None</option>

                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $ingredient->supplier_id) === $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>

                <p class="mt-1 text-sm text-red-700">
                    {{ $errors->first('supplier_id') }}
                </p>
            </div>
        </div>

        <x-form.textarea name="notes" label="Notes (optional)" :value="$ingredient->notes" rows="2" maxlength="500" />

        <input type="hidden" name="is_active" value="0">

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="accent-navy" @checked(old('is_active', $ingredient->is_active))>
            Active
        </label>

        @if ($editing)
            <p class="text-sm">
                To change the stock amount use <strong>Receive</strong>,
                <strong>Waste</strong> or <strong>Stock count</strong> on the
                ingredient's stock page. The cost here can be corrected by hand;
                receiving stock with a price updates it automatically (weighted average).
            </p>
        @endif

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary">
                Save
            </button>

            <a href="{{ $editing ? route('admin.ingredients.show', $ingredient) : route('admin.ingredients.index') }}"
                class="btn border border-navy/20 text-navy">
                Cancel
            </a>
        </div>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.ingredients.destroy', $ingredient) }}"
            data-confirm="Delete {{ $ingredient->name }}?" class="mt-4">
            @csrf
            @method('DELETE')

            <button type="submit" class="btn border border-red-700 text-red-700 hover:bg-red-50">
                Delete ingredient
            </button>
        </form>
    @endif
</x-admin.layout>
```
