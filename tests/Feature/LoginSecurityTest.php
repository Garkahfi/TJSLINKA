<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_login_limits_repeated_failed_attempts(): void
    {
        foreach (['login.store', 'admin.login.store', 'pumk-admin.login.store', 'superadmin.login.store'] as $route) {
            $username = 'unknown-'.str_replace('.', '-', $route);
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->post(route($route), ['username' => $username, 'password' => 'wrong'])
                    ->assertSessionHasErrors('username');
            }
            $response = $this->post(route($route), ['username' => $username, 'password' => 'wrong'])
                ->assertSessionHasErrors('username');
            $message = (string) $response->getSession()->get('errors')->first('username');
            $this->assertStringStartsWith('Terlalu banyak percobaan login. Coba lagi dalam ', $message);
        }
    }

    public function test_pumk_admin_must_rotate_temporary_password_before_working(): void
    {
        $user = User::factory()->create([
            'role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true,
            'username' => 'pumk-temporary', 'password' => Hash::make('TemporaryPassword2026'),
            'must_change_password' => true,
        ]);

        $this->post(route('pumk-admin.login.store'), [
            'username' => 'pumk-temporary', 'password' => 'TemporaryPassword2026',
        ])->assertRedirect(route('pumk-admin.password.edit'));
        $this->get(route('pumk-admin.home'))->assertRedirect(route('pumk-admin.password.edit'));
        $this->get(route('pumk-admin.password.edit'))->assertOk()->assertSee('Ganti Password');

        $this->put(route('pumk-admin.password.update'), [
            'current_password' => 'TemporaryPassword2026',
            'password' => 'NewPrivatePassword2026',
            'password_confirmation' => 'NewPrivatePassword2026',
        ])->assertRedirect(route('pumk-admin.home'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NewPrivatePassword2026', $user->fresh()->password));
        $this->get(route('pumk-admin.home'))->assertOk();
    }

    public function test_super_admin_with_temporary_password_cannot_review_until_it_is_changed(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin', 'is_admin' => true, 'is_active' => true,
            'must_change_password' => true, 'password' => Hash::make('TemporaryPassword2026'),
        ]);

        $this->actingAs($user, 'superadmin')
            ->get(route('superadmin.pumk.index'))->assertRedirect(route('superadmin.profile'));
        $this->get(route('superadmin.profile'))->assertOk()->assertSee('Password sementara wajib diganti');
        $this->put(route('superadmin.password.update'), [
            'current_password' => 'TemporaryPassword2026',
            'password' => 'NewPrivatePassword2026',
            'password_confirmation' => 'NewPrivatePassword2026',
        ])->assertRedirect(route('superadmin.profile'));
        $this->get(route('superadmin.pumk.index'))->assertOk();
    }
}
