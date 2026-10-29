<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/items')->assertRedirect('/admin/login');
        $this->post('/admin/items', [])->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_log_out(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password']);
        }

        // Even the correct password is refused while the lock is active.
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_user_with_a_live_session_is_locked_out(): void
    {
        $this->actingAs(User::factory()->staff()->inactive()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public static function accessMatrix(): array
    {
        $everything = [
            '/admin',
            '/admin/reservations',
            '/admin/orders',
            '/admin/messages',
            '/admin/items',
            '/admin/categories',
            '/admin/content/gallery',
            '/admin/content/coupons',
            '/admin/subscribers',
            '/admin/settings',
            '/admin/users',
            '/admin/activity',
            '/admin/sales',
            '/admin/customers',
            '/admin/ingredients',
            '/admin/expenses',
            '/admin/profit-loss',
            '/admin/content/suppliers',
        ];
        $managerOnly = ['/admin/settings', '/admin/users', '/admin/activity'];
        $guestHandling = ['/admin', '/admin/reservations', '/admin/orders', '/admin/messages'];

        $cases = [];

        foreach ($everything as $url) {
            $cases["admin {$url}"] = ['admin', $url, 200];
            $cases["manager {$url}"] = ['manager', $url, in_array($url, $managerOnly, true) ? 403 : 200];
            $cases["staff {$url}"] = ['staff', $url, in_array($url, $guestHandling, true) ? 200 : 403];
        }

        return $cases;
    }

    #[DataProvider('accessMatrix')]
    public function test_role_access_matrix(string $role, string $url, int $expected): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get($url)->assertStatus($expected);
    }

    public function test_staff_cannot_post_to_manager_routes(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.items.store'), ['name_en' => 'X'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.categories.store'), ['name_en' => 'X'])->assertForbidden();
    }

    public function test_admin_cannot_change_own_role_or_deactivate_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Renamed',
            'email' => $admin->email,
            'role' => 'staff',
            'is_active' => 0,
        ])->assertRedirect();

        $admin->refresh();
        $this->assertSame('Renamed', $admin->name);
        $this->assertSame('admin', $admin->role->value);
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_create_a_user_with_a_strong_password_only(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = ['name' => 'New Manager', 'email' => 'manager@example.com', 'role' => 'manager', 'is_active' => 1];

        $this->actingAs($admin)->post(route('admin.users.store'), $payload + ['password' => 'abc'])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)->post(route('admin.users.store'), $payload + ['password' => 'Str0ng-pass-1'])
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'manager@example.com')->firstOrFail();
        $this->assertSame('manager', $user->role->value);
        $this->assertTrue(Hash::check('Str0ng-pass-1', $user->password));
        $this->assertNotSame('Str0ng-pass-1', $user->password);
    }

    public function test_user_email_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dup',
            'email' => $admin->email,
            'role' => 'staff',
            'is_active' => 1,
            'password' => 'Str0ng-pass-1',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_actions_are_written_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'name_en' => 'Meher Cuisine',
            'phone_en' => '01712345678',
            'email_en' => 'hello@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.updated', 'user_id' => $admin->id]);
    }

    public function test_settings_are_validated_and_shown_on_the_site(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'name_en' => '',
            'phone_en' => 'not a phone',
            'email_en' => 'not-an-email',
            'map_embed_url_en' => 'https://evil.example/map',
        ])->assertSessionHasErrors(['name_en', 'phone_en', 'email_en', 'map_embed_url_en']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'name_en' => 'Meher Cuisine',
            'name_bn' => 'মেহের কুইজিন',
            'phone_en' => '01712345678',
            'email_en' => 'hello@example.com',
        ])->assertRedirect();

        $this->get('/')->assertSee('Meher Cuisine');
        $this->get('/lang/bn');
        $this->get('/')->assertSee('মেহের কুইজিন');
    }
}
