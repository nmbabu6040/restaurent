<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('restaurant.admin.email');
        $user = User::firstOrNew(['email' => $email]);

        if ($user->exists) {
            $this->command?->info("Admin {$email} already exists. Nothing changed.");

            return;
        }

        $password = config('restaurant.admin.password') ?: Str::password(16);

        $user->name = 'Administrator';
        $user->password = $password;
        $user->forceFill(['role' => UserRole::Admin, 'is_active' => true])->save();

        $this->command?->warn("Admin created: {$email}");
        $this->command?->warn("Password: {$password}  (save it now, it is shown only once)");
    }
}
