<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_redirect_guests_to_the_public_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/teras-tjsl')->assertRedirect(route('login'));
        $this->get('/profil')->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="public-login-card"', false)
            ->assertSee('videos/waterfall-bg.mp4')
            ->assertSee('action="'.route('login.store').'"', false);
    }

    public function test_the_same_credentials_can_open_public_and_admin_dashboards_through_different_guards(): void
    {
        $admin = User::factory()->create([
            'username' => 'TJSLINKA',
            'password' => Hash::make('TJSLHPVICTUS'),
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'username' => 'TJSLINKA',
            'password' => 'TJSLHPVICTUS',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get(route('home'))->assertOk();

        $this->post(route('admin.login.store'), [
            'username' => 'TJSLINKA',
            'password' => 'TJSLHPVICTUS',
        ])->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->get(route('admin.home'))->assertOk();
    }

    public function test_public_profile_renders_and_updates_the_shared_account(): void
    {
        $admin = User::factory()->create([
            'name' => 'User Admin',
            'nama_depan' => 'User',
            'nama_belakang' => 'Admin',
            'username' => 'public-admin',
            'password' => Hash::make('Password123'),
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'web')
            ->get(route('public.profile'))
            ->assertOk()
            ->assertSee('class="public-profile-card"', false)
            ->assertSee('User Admin')
            ->assertSee('Informasi Pribadi')
            ->assertSee('Ubah Password');

        $this->put(route('public.profile.update'), [
            'nama_depan' => 'User',
            'nama_belakang' => 'Publik',
            'email' => 'public@example.com',
            'no_telephone' => '08123456789',
            'jabatan' => 'Karyawan',
            'alamat' => 'Madiun',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'User Publik',
            'email' => 'public@example.com',
        ]);
    }
}
