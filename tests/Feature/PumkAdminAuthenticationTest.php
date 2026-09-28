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

    public function test_pumk_admin_login_uses_web_and_pumk_guards_for_the_same_account(): void
    {
        $pumkAdmin = $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->post(route('pumk-admin.login.store'), [
            'username' => 'admin-pumk',
            'password' => 'PumkPassword2026',
        ])->assertRedirect(route('pumk-admin.home'));

        $this->assertAuthenticatedAs($pumkAdmin, 'pumk');
        $this->assertAuthenticatedAs($pumkAdmin, 'web');
        $this->assertGuest('admin');
        $this->get(route('pumk-admin.home'))
            ->assertOk()
            ->assertSee('Dashboard Admin PUMK')
            ->assertDontSee('Overview Program TJSL');
    }

    public function test_legacy_pumk_login_url_routes_admin_tjsl_to_its_own_dashboard(): void
    {
        $this->user('admin', 'admin-tjsl', 'AdminPassword2026');

        $this->from(route('pumk-admin.login'))
            ->post(route('pumk-admin.login.store'), [
                'username' => 'admin-tjsl',
                'password' => 'AdminPassword2026',
            ])
            ->assertRedirect(route('admin.home'));

        $this->assertGuest('pumk');
    }

    public function test_legacy_admin_login_url_routes_pumk_admin_to_its_own_dashboard(): void
    {
        $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'username' => 'admin-pumk',
                'password' => 'PumkPassword2026',
            ])
            ->assertRedirect(route('pumk-admin.home'));

        $this->assertGuest('admin');
    }

    public function test_role_middleware_blocks_cross_panel_access(): void
    {
        $admin = $this->user('admin', 'admin-tjsl', 'AdminPassword2026');

        $this->actingAs($admin, 'web')
            ->get(route('pumk-admin.home'))
            ->assertForbidden();

        $pumkAdmin = $this->user('pumk_admin', 'admin-pumk', 'PumkPassword2026');

        $this->actingAs($pumkAdmin, 'web')
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

    public function test_switching_to_pumk_account_replaces_admin_and_logout_ends_all_access(): void
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

        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($pumkAdmin, 'web');
        $this->assertAuthenticatedAs($pumkAdmin, 'pumk');
        $this->get(route('admin.home'))->assertForbidden();
        $this->get(route('pumk-admin.home'))->assertOk();

        $this->post(route('pumk-admin.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest('pumk');
        $this->assertGuest('admin');
        $this->assertGuest('web');
        $this->get(route('admin.home'))->assertRedirect(route('login'));
    }

    public function test_pumk_admin_profile_uses_the_shared_profile_and_password_forms(): void
    {
        $pumkAdmin = $this->user('pumk_admin', 'profile-pumk', 'PumkPassword2026');

        $this->actingAs($pumkAdmin, 'pumk')
            ->get(route('pumk-admin.profile'))
            ->assertOk()
            ->assertSee('Informasi Pribadi')
            ->assertSee('data-profile-form', false)
            ->assertSee('data-password-section', false)
            ->assertSee('Password Lama')
            ->assertSee('Password Baru')
            ->assertSee('Konfirmasi Password Baru');

        $this->get(route('pumk-admin.password.edit'))
            ->assertRedirect(route('pumk-admin.profile'));

        $this->put(route('pumk-admin.profile.update'), [
            'nama_depan' => 'Admin',
            'nama_belakang' => 'PUMK',
            'email' => 'admin.pumk.profile@example.test',
            'no_telephone' => '081234567890',
            'jabatan' => 'Admin PUMK',
            'alamat' => 'PT INKA',
        ])->assertRedirect();

        $pumkAdmin->refresh();
        $this->assertSame('Admin PUMK', $pumkAdmin->name);
        $this->assertSame('admin.pumk.profile@example.test', $pumkAdmin->email);

        $this->get(route('pumk-admin.home'))
            ->assertOk()
            ->assertSee(route('pumk-admin.profile'), false)
            ->assertSee('aria-label="Profil"', false);
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
