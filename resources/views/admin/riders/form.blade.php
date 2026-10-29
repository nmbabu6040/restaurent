@php $editing = $rider->exists; @endphp

<x-admin.layout :title="$editing ? 'Edit rider' : 'New rider'">
    <form method="POST" action="{{ $editing ? route('admin.riders.update', $rider) : route('admin.riders.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="name" label="Name" :required="true" :value="$rider->name" maxlength="100" />
            <x-form.field name="phone" type="tel" label="Phone" :required="true" :value="$rider->phone"
                maxlength="20" />

            <div>
                <label for="vehicle" class="block text-sm font-medium text-navy">Vehicle</label>
                <select id="vehicle" name="vehicle"
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                    @foreach (\App\Models\Rider::VEHICLES as $value => $label)
                        <option value="{{ $value }}" @selected(old('vehicle', $rider->vehicle) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-red-700">{{ $errors->first('vehicle') }}</p>
            </div>
            <x-form.field name="vehicle_number" label="Vehicle number (optional)" :value="$rider->vehicle_number" maxlength="30" />

            <x-form.field name="fee_per_delivery" type="number" label="Fee per delivery" :required="true"
                :value="$rider->fee_per_delivery" step="0.01" min="0" />
        </div>

        <x-form.textarea name="notes" label="Notes (optional)" :value="$rider->notes" rows="2" maxlength="500" />

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="accent-navy" @checked(old('is_active', $rider->is_active))>
            Active (can receive deliveries)
        </label>

        <fieldset class="rounded-xl border border-navy/10 p-4">
            <legend class="px-1 font-heading text-lg text-navy">Rider login</legend>
            <p class="text-sm">The rider signs in at <strong>/admin/login</strong> and lands on their own panel. Leave
                empty if they do not need a login.</p>
            <div class="mt-3 grid gap-5 md:grid-cols-2">
                <x-form.field name="login_email" type="email" label="Login email" :value="$rider->user?->email" maxlength="150"
                    autocomplete="off" />
                <x-form.field name="login_password" type="password" :label="$rider->user_id
                    ? 'New password (leave empty to keep)'
                    : 'Password (min 10 characters, letters and numbers)'" autocomplete="new-password" />
            </div>
        </fieldset>

        <p class="text-sm">Changing the fee only affects future assignments. Deliveries already given keep the fee they
            were assigned with.</p>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ $editing ? route('admin.riders.show', $rider) : route('admin.riders.index') }}"
                class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.riders.destroy', $rider) }}"
            data-confirm="Remove {{ $rider->name }}? Their delivery history is kept." class="mt-4">
            @csrf @method('DELETE')
            <button type="submit" class="btn border border-red-700 text-red-700 hover:bg-red-50">Remove rider</button>
        </form>
    @endif
</x-admin.layout>
