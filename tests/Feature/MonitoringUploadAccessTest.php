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
            ->assertRedirect(route('login'));

        $this->actingAs($adminTjsl, 'admin')
            ->get('/admin/monitoring/upload')
            ->assertForbidden();

        $this->actingAs($pumkAdmin, 'pumk')
            ->get('/admin/monitoring/upload')
            ->assertOk()
            ->assertSee('Upload Data Monitoring')
            ->assertSee('Sheet yang tidak tersedia akan dilewati')
            ->assertDontSee('Nama sheet dan header yang diterima');
    }

    public function test_wrong_role_cannot_open_pumk_upload(): void
    {
        $adminTjsl = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($adminTjsl, 'web')
            ->get('/admin/monitoring/upload')
            ->assertForbidden();
    }
}
