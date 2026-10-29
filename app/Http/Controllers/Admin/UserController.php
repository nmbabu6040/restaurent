<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;


class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $user = new User(Arr::only($data, ['name', 'email']));
        $user->password = $data['password'];
        $user->forceFill(['role' => $data['role'], 'is_active' => $data['is_active']])->save();

        ActivityLog::record('user.created', "Created user {$user->email} ({$data['role']})", $user);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user));

        $user->fill(Arr::only($data, ['name', 'email']));

        // You cannot change your own role or deactivate yourself; this also guarantees an active admin always remains.
        if (! $user->is($request->user())) {
            $user->forceFill(['role' => $data['role'], 'is_active' => $data['is_active']]);
        }

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        ActivityLog::record('user.updated', "Updated user {$user->email}", $user);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();
        ActivityLog::record('user.deleted', "Deleted user {$user->email}");

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
        ];
    }
}
