@php $editing = $expense->exists; @endphp

<x-admin.layout :title="$editing ? 'Edit expense' : 'New expense'">
    <form method="POST"
        action="{{ $editing ? route('admin.expenses.update', $expense) : route('admin.expenses.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="expense_date" type="date" label="Date" :required="true" :value="$expense->expense_date?->toDateString()"
                max="{{ now()->toDateString() }}" />

            <div>
                <label for="category" class="block text-sm font-medium text-navy">Category<span aria-hidden="true"
                        class="text-terracotta-dark"> *</span></label>
                <select id="category" name="category" required
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                    <option value="">Choose…</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $expense->category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-red-700">{{ $errors->first('category') }}</p>
            </div>

            <x-form.field name="amount" type="number" label="Amount" :required="true" :value="$expense->amount"
                step="0.01" min="0.01" />

            <div>
                <label for="supplier_id" class="block text-sm font-medium text-navy">Paid to (optional)</label>
                <select id="supplier_id" name="supplier_id"
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                    <option value="">None</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $expense->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-form.field name="description" label="Description (optional)" :value="$expense->description" maxlength="255" />

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.expenses.index') }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
