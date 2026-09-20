<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringUploadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_pumk_admin_can_open_exact_monitoring_upload_url(): void
    {
        $pumkAdmin = User::factory()->create([
            'role' => 'pumk_admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $adminTjsl = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->get('/admin/monitoring/upload')
            ->assertRedirect('/admin-pumk/login');

        $this->actingAs($adminTjsl, 'admin')
            ->get('/admin/monitoring/upload')
            ->assertRedirect('/admin-pumk/login');

        $this->actingAs($pumkAdmin, 'pumk')
            ->get('/admin/monitoring/upload')
            ->assertOk()
            ->assertSee('Upload Data Monitoring')
            ->assertSee('Sheet yang tidak tersedia akan dilewati')
            ->assertDontSee('Nama sheet dan header yang diterima');
    }

    public function test_wrong_role_forced_into_pumk_guard_is_forbidden(): void
    {
        $adminTjsl = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($adminTjsl, 'pumk')
            ->get('/admin/monitoring/upload')
            ->assertForbidden();
    }
}
