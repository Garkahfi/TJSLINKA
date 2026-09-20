<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'username' => 'TJSLINKA',
            'password' => Hash::make('TJSLHPVICTUS'),
            'is_admin' => true,
            'must_change_password' => true,
        ]);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_with_temporary_password_must_change_it_before_opening_dashboard(): void
    {
        $this->admin();

        $this->post('/admin/login', [
            'username' => 'TJSLINKA',
            'password' => 'TJSLHPVICTUS',
        ])->assertRedirect(route('admin.profile'));

        $this->get('/admin/home')->assertRedirect(route('admin.profile'));
        $this->get(route('admin.profile'))->assertOk()->assertSee('Password sementara wajib diganti');
    }

    public function test_admin_can_change_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->put('/admin/ganti-password', [
            'current_password' => 'TJSLHPVICTUS',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect(route('admin.profile'));

        $admin->refresh();
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('PasswordBaru123', $admin->password));
    }

    public function test_admin_and_super_admin_sessions_are_independent(): void
    {
        $admin = $this->admin();
        $admin->update(['must_change_password' => false]);
        $superAdmin = User::factory()->create([
            'username' => 'TJSLINKAMIN',
            'password' => Hash::make('TJSLHPVICTUS15'),
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->post(route('admin.login.store'), [
            'username' => 'TJSLINKA',
            'password' => 'TJSLHPVICTUS',
        ])->assertRedirect(route('admin.home'));

        $this->get(route('superadmin.login'))->assertOk();
        $this->post(route('superadmin.login.store'), [
            'username' => 'TJSLINKAMIN',
            'password' => 'TJSLHPVICTUS15',
        ])->assertRedirect(route('superadmin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($superAdmin, 'superadmin');
        $this->get(route('admin.home'))->assertOk();
        $this->get(route('superadmin.home'))->assertOk();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($superAdmin, 'superadmin');
        $this->get(route('superadmin.home'))->assertOk();
    }

    public function test_admin_login_ignores_stale_super_admin_intended_url(): void
    {
        $this->admin();

        $this->withSession(['url.intended' => route('superadmin.home')])
            ->post(route('admin.login.store'), [
                'username' => 'TJSLINKA',
                'password' => 'TJSLHPVICTUS',
            ])->assertRedirect(route('admin.profile'))
            ->assertSessionMissing('url.intended');
    }

    public function test_admin_and_super_admin_profiles_contain_the_inline_password_form(): void
    {
        $admin = $this->admin();
        $superAdmin = User::factory()->create([
            'username' => 'super-password-design',
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $adminPage = $this->actingAs($admin, 'admin')->get(route('admin.profile'));
        $adminPage->assertOk()
            ->assertSee('data-password-section', false)
            ->assertSee('data-password-edit', false)
            ->assertSee('Password Lama')
            ->assertSee('Password Baru')
            ->assertSee('Konfirmasi Password Baru')
            ->assertSee('Simpan')
            ->assertSee('Batal');
        $this->get(route('admin.password.edit'))->assertRedirect(route('admin.profile'));

        $superAdminPage = $this->actingAs($superAdmin, 'superadmin')->get(route('superadmin.profile'));
        $superAdminPage->assertOk()
            ->assertSee('data-password-section', false)
            ->assertSee('data-password-edit', false)
            ->assertSee('Password Lama')
            ->assertSee('Password Baru')
            ->assertSee('Konfirmasi Password Baru')
            ->assertSee('Simpan')
            ->assertSee('Batal');
        $this->get(route('superadmin.password.edit'))->assertRedirect(route('superadmin.profile'));
    }
}
