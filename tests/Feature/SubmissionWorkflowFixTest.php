<?php

namespace Tests\Feature;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionWorkflowFixTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function pillar(): Pillar
    {
        return Pillar::create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
        ]);
    }

    public function test_program_photo_is_saved_with_bast_and_accepts_up_to_twenty_mb(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.programs.store'), [
            'pillar_id' => $this->pillar()->id,
            'nama_program' => 'Program Foto Besar',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 0,
            'tujuan_program' => 'Tujuan',
            'documents' => [
                'proposal_pengajuan_program' => [
                    'nama' => '',
                    'file' => UploadedFile::fake()->create('surat-laporan.pdf', 100, 'application/pdf'),
                ],
            ],
            'photo_caption' => 'Dokumentasi kegiatan lapangan',
            'photos' => [UploadedFile::fake()->image('dokumentasi.jpg')->size(6144)],
            'action' => 'draft',
        ]);

        $response->assertSessionHasNoErrors();
        $program = Program::firstOrFail();
        $this->assertCount(0, $program->photos);
        $this->assertSame('surat-laporan.pdf', $program->documents->first()->nama_dokumen);

        $program->update(['status' => 'approved_fase1']);

        $this->actingAs($program->creator, 'admin')->post(route('admin.programs.bast.store', $program), [
            'dokumen_f' => UploadedFile::fake()->create('bast.pdf', 100, 'application/pdf'),
            'phase2_photo_captions' => ['Dokumentasi kegiatan lapangan'],
            'phase2_photos' => [UploadedFile::fake()->image('dokumentasi.jpg')->size(6144)],
        ])->assertSessionHasNoErrors();

        $program->refresh();
        $this->assertCount(1, $program->photos);
        $this->assertSame('Dokumentasi kegiatan lapangan', $program->photos->first()->caption);
    }

    public function test_cancelled_admin_submissions_disappear_from_super_admin_lists(): void
    {
        $admin = $this->admin();
        $superAdmin = $this->superAdmin();
        $program = Program::create([
            'slug' => 'dibatalkan-admin',
            'pillar_id' => $this->pillar()->id,
            'nama_program' => 'Program Dibatalkan Admin',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000000,
            'tujuan_program' => 'Tujuan',
            'status' => 'pending_fase1',
            'created_by' => $admin->id,
        ]);
        $assistance = BantuanCsr::create([
            'nama_program_bantuan' => 'Bantuan Dibatalkan Admin',
            'deskripsi_bantuan' => 'Deskripsi',
            'rencana_anggaran' => 1000000,
            'status' => 'pending_fase1',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.programs.cancel', $program))->assertRedirect();
        $this->post(route('admin.assistance.cancel', $assistance))->assertRedirect();

        $this->actingAs($superAdmin, 'superadmin')->get(route('superadmin.programs.index'))
            ->assertOk()->assertDontSee('Program Dibatalkan Admin');
        $this->get(route('superadmin.assistance.index'))
            ->assertOk()->assertDontSee('Bantuan Dibatalkan Admin');
        $this->get(route('superadmin.home'))
            ->assertOk()
            ->assertDontSee('Program Dibatalkan Admin')
            ->assertDontSee('Bantuan Dibatalkan Admin');
    }

    public function test_admin_program_list_renders_text_and_icons_for_all_statuses(): void
    {
        $admin = $this->admin();
        $pillar = $this->pillar();
        foreach (['draft', 'pending_fase1', 'approved_fase1', 'pending_fase2', 'completed'] as $status) {
            Program::create([
                'slug' => 'status-'.$status,
                'pillar_id' => $pillar->id,
                'nama_program' => 'Program '.$status,
                'deskripsi_program' => 'Deskripsi',
                'sasaran_program' => 'Sasaran',
                'lokasi_program' => 'Madiun',
                'mitra_program' => 'Mitra',
                'rencana_anggaran' => 1,
                'tujuan_program' => 'Tujuan',
                'status' => $status,
                'created_by' => $admin->id,
            ]);
        }

        $response = $this->actingAs($admin, 'admin')->get(route('admin.programs.index'));
        $response->assertOk()
            ->assertSee('data-status="draft"', false)
            ->assertSee('data-status="pending_fase1"', false)
            ->assertSee('data-status="approved_fase1"', false)
            ->assertSee('data-status="pending_fase2"', false)
            ->assertSee('data-status="completed"', false)
            ->assertSee('Waiting')
            ->assertSee('<svg', false);
        foreach (['draft', 'pending_fase1', 'approved_fase1', 'pending_fase2', 'completed'] as $status) {
            $response->assertSee('data-program-status="'.$status.'"', false);
        }

        $this->assertSame(5, substr_count($response->getContent(), 'background:'.$pillar->color_hex));
    }
}
