@php
    $editing = $user->exists;
    $self = $editing && $user->is(auth()->user());
@endphp

<x-admin.layout :title="$editing ? 'Edit user' : 'New user'">
    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="name" label="Name" :required="true" :value="$user->name" maxlength="100" />
            <x-form.field name="email" type="email" label="Email" :required="true" :value="$user->email" maxlength="150"
                autocomplete="off" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="role" class="block text-sm font-medium text-navy">Role<span aria-hidden="true"
                        class="text-terracotta-dark"> *</span></label>
                <select id="role" name="role" required @disabled($self)
                    aria-describedby="role-error"
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta disabled:opacity-60">
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? 'staff') === $role->value)>{{ ucfirst($role->value) }}
                        </option>
                    @endforeach
                </select>
                @if ($self)
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                @endif
                <p id="role-error" class="mt-1 text-sm text-red-700">{{ $errors->first('role') }}</p>
            </div>

            <x-form.field name="password" type="password" :label="$editing
                ? 'New password (leave empty to keep current)'
                : 'Password (min 10 characters, letters and numbers)'" :required="!$editing"
                autocomplete="new-password" />
        </div>

        <input type="hidden" name="is_active" value="{{ $self ? 1 : 0 }}">
        @unless ($self)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="accent-navy" @checked(old('is_active', $editing ? $user->is_active : true))>
                Active (can log in)
            </label>
        @endunless

        <p class="text-sm">Admin: everything. Manager: menu and website content. Staff: reservations and messages only.
        </p>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.users.index') }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
