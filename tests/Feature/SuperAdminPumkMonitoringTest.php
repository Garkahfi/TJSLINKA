<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPumkMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_monitor_pumk_without_sensitive_identity_or_write_actions(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $pumk = User::factory()->create(['role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Pantau', 'source_key' => hash('sha256', 'mitra-pantau'),
            'no_ktp_encrypted' => '1234567890123456', 'no_telepon_encrypted' => '08123456789',
            'no_rekening_encrypted' => '1234567890', 'alamat' => 'Alamat Terbatas',
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'pinjaman-pantau'),
            'pinjaman_pokok' => 1000000, 'pinjaman_bunga' => 100000,
            'mulai_angsuran' => '2026-01-01', 'selesai_angsuran' => '2026-02-01',
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2026-01-01',
            'pokok' => 100000, 'bunga' => 10000, 'created_by' => $pumk->id,
        ]);

        $this->actingAs($super, 'superadmin')
            ->get(route('superadmin.pumk.index'))
            ->assertOk()->assertSee('Monitoring Admin PUMK')->assertSee('Mitra Pantau');

        $this->get(route('superadmin.pumk.mitra'))
            ->assertOk()->assertSee('Mitra Pantau')->assertDontSee('1234567890123456');

        $this->get(route('superadmin.pumk.kartu', $mitra))
            ->assertOk()->assertSee('Mitra Pantau')->assertDontSee('08123456789')
            ->assertDontSee('1234567890')->assertDontSee('Alamat Terbatas')
            ->assertDontSee('Tambah Angsuran')->assertDontSee('Edit Data');

        $this->get(route('pumk-admin.home'))->assertRedirect(route('pumk-admin.login'));
        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), [])->assertRedirect(route('pumk-admin.login'));
    }

    public function test_other_roles_cannot_open_super_admin_pumk_monitoring(): void
    {
        $pumk = User::factory()->create(['role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true]);
        $this->actingAs($pumk, 'superadmin')->get(route('superadmin.pumk.index'))->assertForbidden();
    }
}
