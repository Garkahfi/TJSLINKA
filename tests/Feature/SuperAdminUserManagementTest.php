<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_index_lists_tjsl_and_pumk_admin_accounts_but_not_super_admins(): void
    {
        $superAdmin = $this->superAdmin();
        $this->adminAccount('admin', 'admin-tjsl-list');
        $this->adminAccount('pumk_admin', 'admin-pumk-list');

        $this->actingAs($superAdmin, 'superadmin')
            ->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee('admin-tjsl-list')
            ->assertSee('admin-pumk-list')
            ->assertSee('Admin TJSL')
            ->assertSee('Admin PUMK')
            ->assertDontSee($superAdmin->username);
    }

    public function test_super_admin_can_create_a_pumk_admin_account(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'superadmin')
            ->post(route('superadmin.users.store'), $this->payload([
                'role' => 'pumk_admin',
                'username' => 'pumk-baru',
                'email' => 'pumk-baru@example.test',
                'password' => 'PasswordPumk2026',
            ]));

        $response->assertRedirect(route('superadmin.users.index'));

        $account = User::where('username', 'pumk-baru')->firstOrFail();
        $this->assertSame('pumk_admin', $account->role);
        $this->assertTrue($account->is_admin);
        $this->assertTrue($account->is_active);
        $this->assertTrue($account->must_change_password);
        $this->assertTrue(Hash::check('PasswordPumk2026', $account->password));
    }

    public function test_create_and_edit_forms_offer_only_the_two_managed_account_types(): void
    {
        $superAdmin = $this->superAdmin();
        $pumkAdmin = $this->adminAccount('pumk_admin', 'pumk-form');

        $this->actingAs($superAdmin, 'superadmin')
            ->get(route('superadmin.users.create'))
            ->assertOk()
            ->assertSee('value="admin"', false)
            ->assertSee('value="pumk_admin"', false)
            ->assertDontSee('value="super_admin"', false);

        $this->get(route('superadmin.users.edit', $pumkAdmin))
            ->assertOk()
            ->assertSee('value="pumk_admin" selected', false);
    }

    public function test_super_admin_can_edit_and_deactivate_a_pumk_admin_account(): void
    {
        $superAdmin = $this->superAdmin();
        $pumkAdmin = $this->adminAccount('pumk_admin', 'pumk-lama');
        $passwordBefore = $pumkAdmin->password;

        $this->actingAs($superAdmin, 'superadmin')
            ->put(route('superadmin.users.update', $pumkAdmin), $this->payload([
                'role' => 'pumk_admin',
                'nama_depan' => 'Admin',
                'nama_belakang' => 'Kemitraan',
                'username' => 'pumk-diperbarui',
                'email' => 'pumk-diperbarui@example.test',
            ]))
            ->assertRedirect(route('superadmin.users.index'));

        $pumkAdmin->refresh();
        $this->assertSame('pumk_admin', $pumkAdmin->role);
        $this->assertSame('Admin Kemitraan', $pumkAdmin->name);
        $this->assertSame('pumk-diperbarui', $pumkAdmin->username);
        $this->assertSame($passwordBefore, $pumkAdmin->password);

        $this->delete(route('superadmin.users.destroy', $pumkAdmin))
            ->assertRedirect();

        $this->assertFalse($pumkAdmin->fresh()->is_active);
    }

    public function test_super_admin_can_change_a_managed_account_type(): void
    {
        $superAdmin = $this->superAdmin();
        $admin = $this->adminAccount('admin', 'admin-beralih-pumk');

        $this->actingAs($superAdmin, 'superadmin')
            ->put(route('superadmin.users.update', $admin), $this->payload([
                'role' => 'pumk_admin',
                'username' => $admin->username,
                'email' => $admin->email,
            ]))
            ->assertRedirect(route('superadmin.users.index'));

        $this->assertSame('pumk_admin', $admin->fresh()->role);
    }

    public function test_role_validation_rejects_privilege_escalation_to_super_admin(): void
    {
        $this->actingAs($this->superAdmin(), 'superadmin')
            ->post(route('superadmin.users.store'), $this->payload([
                'role' => 'super_admin',
                'username' => 'super-tidak-sah',
                'email' => 'super-tidak-sah@example.test',
                'password' => 'PasswordTidakSah2026',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'super-tidak-sah']);
    }

    public function test_user_management_routes_cannot_modify_a_super_admin_account(): void
    {
        $currentSuperAdmin = $this->superAdmin('super-current');
        $otherSuperAdmin = $this->superAdmin('super-other');

        $this->actingAs($currentSuperAdmin, 'superadmin')
            ->get(route('superadmin.users.edit', $otherSuperAdmin))
            ->assertNotFound();

        $this->delete(route('superadmin.users.destroy', $otherSuperAdmin))
            ->assertNotFound();

        $this->assertTrue($otherSuperAdmin->fresh()->is_active);
        $this->assertSame('super_admin', $otherSuperAdmin->fresh()->role);
    }

    private function superAdmin(string $username = 'super-user-management'): User
    {
        return User::factory()->create([
            'name' => 'Super Admin',
            'username' => $username,
            'email' => $username.'@example.test',
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function adminAccount(string $role, string $username): User
    {
        return User::factory()->create([
            'name' => $username,
            'nama_depan' => 'Admin',
            'nama_belakang' => 'Test',
            'username' => $username,
            'email' => $username.'@example.test',
            'role' => $role,
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'role' => 'admin',
            'nama_depan' => 'User',
            'nama_belakang' => 'Admin',
            'username' => 'user-admin-baru',
            'email' => 'user-admin-baru@example.test',
            'jabatan' => 'Karyawan',
            'no_telephone' => '08123456789',
            'alamat' => 'Madiun',
            'password' => 'PasswordAdmin2026',
        ], $overrides);
    }
}
