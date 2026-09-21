<?php

namespace Tests\Feature;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TjslStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin', 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
    }

    public function test_admin_program_filter_maps_each_business_status_without_exposing_rejected_by_default(): void
    {
        $this->program('Program Draft', 'draft');
        $this->program('Program Menunggu Tahap 1', 'pending_fase1');
        $this->program('Program ACC Tahap 1', 'approved_fase1');
        $this->program('Program BAST Ditolak', 'approved_fase1', 'BAST perlu diperbaiki.');
        $this->program('Program Menunggu Tahap 2', 'pending_fase2');
        $this->program('Program Ditolak Tahap 1', 'rejected_fase1');
        $this->program('Program Selesai', 'completed');

        $base = $this->actingAs($this->admin, 'admin')->get(route('admin.programs.index'));
        $base->assertOk()->assertSee('Semua Status Aktif')->assertDontSee('Program Ditolak Tahap 1');

        $this->get(route('admin.programs.index', ['status' => 'waiting']))
            ->assertOk()->assertSee('Program Menunggu Tahap 1')->assertSee('Program Menunggu Tahap 2')
            ->assertDontSee('Program ACC Tahap 1')->assertDontSee('Program Selesai');
        $this->get(route('admin.programs.index', ['status' => 'approved_phase1']))
            ->assertOk()->assertSee('Program ACC Tahap 1')->assertDontSee('Program BAST Ditolak');
        $this->get(route('admin.programs.index', ['status' => 'rejected']))
            ->assertOk()->assertSee('Program Ditolak Tahap 1')->assertSee('Program BAST Ditolak')
            ->assertSee('BAST Ditolak')->assertDontSee('Program Selesai');
        $this->get(route('admin.programs.index', ['status' => 'completed']))
            ->assertOk()->assertSee('Program Selesai')->assertDontSee('Program Menunggu Tahap 1');
    }

    public function test_superadmin_program_filter_is_submitted_only_and_keeps_pillar_query(): void
    {
        $this->program('Draft Privat', 'draft');
        $this->program('Pengajuan Menunggu', 'pending_fase1');
        $this->program('Pengajuan Ditolak', 'rejected_fase1');
        $this->program('Pengajuan Selesai', 'completed');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.index'))
            ->assertOk()->assertDontSee('Draft Privat')->assertDontSee('Pengajuan Ditolak');
        $this->get(route('superadmin.programs.index', ['status' => 'rejected', 'pillar' => 'sosial']))
            ->assertOk()->assertSee('Pengajuan Ditolak')->assertDontSee('Draft Privat')
            ->assertSee('name="pillar" value="sosial"', false);
        $this->get(route('superadmin.programs.index', ['status' => 'draft']))
            ->assertRedirect()->assertSessionHasErrors('status');
    }

    public function test_admin_and_superadmin_assistance_filters_include_phase_one_and_bast_rejections(): void
    {
        $this->assistance('Bantuan Draft', 'draft');
        $this->assistance('Bantuan Menunggu', 'pending_fase1');
        $this->assistance('Bantuan ACC Tahap 1', 'approved_fase1');
        $this->assistance('Bantuan BAST Ditolak', 'approved_fase1', 'BAST belum lengkap.');
        $this->assistance('Bantuan Ditolak Tahap 1', 'rejected_fase1');
        $this->assistance('Bantuan Selesai', 'completed');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.index', ['status' => 'rejected']))
            ->assertOk()->assertSee('Bantuan Ditolak Tahap 1')->assertSee('Bantuan BAST Ditolak')
            ->assertDontSee('Bantuan Selesai');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.assistance.index', ['status' => 'completed']))
            ->assertOk()->assertSee('Bantuan Selesai')->assertDontSee('Bantuan Draft')
            ->assertDontSee('Bantuan Menunggu');
        $this->get(route('superadmin.assistance.index', ['status' => 'rejected']))
            ->assertOk()->assertSee('Bantuan Ditolak Tahap 1')->assertSee('Bantuan BAST Ditolak')
            ->assertSee('BAST Ditolak');
    }

    private function program(string $name, string $status, ?string $phaseTwoReason = null): Program
    {
        return Program::create([
            'slug' => str($name)->slug(), 'pillar_id' => $this->pillar->id, 'jenis_kerjasama' => 'pks',
            'nama_program' => $name, 'deskripsi_program' => 'Deskripsi', 'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun', 'mitra_program' => 'Mitra', 'rencana_anggaran' => 100,
            'status' => $status, 'fase2_rejected_reason' => $phaseTwoReason, 'created_by' => $this->admin->id,
        ]);
    }

    private function assistance(string $name, string $status, ?string $phaseTwoReason = null): BantuanCsr
    {
        return BantuanCsr::create([
            'nama_program_bantuan' => $name, 'deskripsi_bantuan' => 'Deskripsi',
            'pillar_id' => $this->pillar->id, 'rencana_anggaran' => 100, 'status' => $status,
            'fase2_rejected_reason' => $phaseTwoReason, 'created_by' => $this->admin->id,
        ]);
    }
}
