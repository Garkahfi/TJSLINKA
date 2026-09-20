<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PumkAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PumkAdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pumk_admin_can_login_only_through_the_pumk_guard(): void
    {
        $pumkAdmin = $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->post(route('pumk-admin.login.store'), [
            'username' => 'admin-pumk',
            'password' => 'PumkPassword2026',
        ])->assertRedirect(route('pumk-admin.home'));

        $this->assertAuthenticatedAs($pumkAdmin, 'pumk');
        $this->assertGuest('admin');
        $this->get(route('pumk-admin.home'))
            ->assertOk()
            ->assertSee('Dashboard Admin PUMK')
            ->assertDontSee('Overview Program TJSL');
    }

    public function test_admin_tjsl_credentials_are_rejected_by_pumk_login(): void
    {
        $this->user('admin', 'admin-tjsl', 'AdminPassword2026');

        $this->from(route('pumk-admin.login'))
            ->post(route('pumk-admin.login.store'), [
                'username' => 'admin-tjsl',
                'password' => 'AdminPassword2026',
            ])
            ->assertRedirect(route('pumk-admin.login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest('pumk');
    }

    public function test_pumk_admin_credentials_are_rejected_by_tjsl_admin_login(): void
    {
        $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'username' => 'admin-pumk',
                'password' => 'PumkPassword2026',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest('admin');
    }

    public function test_role_middleware_blocks_a_user_forced_into_the_wrong_guard(): void
    {
        $admin = $this->user('admin', 'admin-tjsl', 'AdminPassword2026');

        $this->actingAs($admin, 'pumk')
            ->get(route('pumk-admin.home'))
            ->assertForbidden();

        $pumkAdmin = $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->actingAs($pumkAdmin, 'admin')
            ->get(route('admin.home'))
            ->assertForbidden();
    }

    public function test_inactive_pumk_admin_cannot_login(): void
    {
        $this->user('pumk_admin', 'inactive-pumk', 'PumkPassword2026', false);

        $this->post(route('pumk-admin.login.store'), [
            'username' => 'inactive-pumk',
            'password' => 'PumkPassword2026',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('pumk');
    }

    public function test_admin_and_pumk_sessions_can_coexist_and_pumk_logout_does_not_logout_admin(): void
    {
        $admin = $this->user('admin', 'admin-tjsl', 'AdminPassword2026');
        $pumkAdmin = $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->post(route('admin.login.store'), [
            'username' => 'admin-tjsl',
            'password' => 'AdminPassword2026',
        ])->assertRedirect(route('admin.home'));

        $this->post(route('pumk-admin.login.store'), [
            'username' => 'admin-pumk',
            'password' => 'PumkPassword2026',
        ])->assertRedirect(route('pumk-admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($pumkAdmin, 'pumk');
        $this->get(route('admin.home'))->assertOk();
        $this->get(route('pumk-admin.home'))->assertOk();

        $this->post(route('pumk-admin.logout'))
            ->assertRedirect(route('pumk-admin.login'));

        $this->assertGuest('pumk');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->get(route('admin.home'))->assertOk();
    }

    public function test_pumk_admin_seeder_is_idempotent(): void
    {
        config()->set('pumk.admin.username', 'seeded-pumk');
        config()->set('pumk.admin.email', 'seeded-pumk@example.test');
        config()->set('pumk.admin.password', 'SeedPassword2026');

        $seeder = app(PumkAdminSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'username' => 'seeded-pumk',
            'role' => 'pumk_admin',
            'is_active' => true,
        ]);
    }

    public function test_seeder_promotes_existing_admin_without_overwriting_profile_and_retires_bootstrap_account(): void
    {
        $existing = $this->user('admin', 'labubu123', 'OldPassword2026');
        $existing->forceFill([
            'name' => 'Nama Tetap',
            'email' => 'profile-existing@example.test',
        ])->save();
        $legacy = $this->user('pumk_admin', 'PUMKADMIN', 'LegacyPassword2026');
        $legacy->forceFill(['email' => 'pumk.admin@tjslinka.local'])->save();

        config()->set('pumk.admin.username', 'labubu123');
        config()->set('pumk.admin.email', 'new-email-must-not-replace@example.test');
        config()->set('pumk.admin.password', 'PumkBootstrap2026');
        config()->set('pumk.admin.legacy_username', 'PUMKADMIN');
        config()->set('pumk.admin.legacy_email', 'pumk.admin@tjslinka.local');

        app(PumkAdminSeeder::class)->run();

        $existing->refresh();
        $legacy->refresh();
        $this->assertSame('pumk_admin', $existing->role);
        $this->assertSame('Nama Tetap', $existing->name);
        $this->assertSame('profile-existing@example.test', $existing->email);
        $this->assertTrue($existing->is_active);
        $this->assertTrue($existing->must_change_password);
        $this->assertTrue(Hash::check('PumkBootstrap2026', $existing->password));
        $this->assertFalse($legacy->is_active);

        $existing->password = Hash::make('PasswordBaruSesudahSeeder2026');
        $existing->save();
        app(PumkAdminSeeder::class)->run();

        $this->assertTrue(Hash::check('PasswordBaruSesudahSeeder2026', $existing->fresh()->password));
    }

    private function user(string $role, string $username, string $password, bool $active = true): User
    {
        return User::factory()->create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make($password),
            'role' => $role,
            'is_admin' => true,
            'is_active' => $active,
            'must_change_password' => false,
        ]);
    }
}
