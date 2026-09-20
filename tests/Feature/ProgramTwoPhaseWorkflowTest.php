<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramTwoPhaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->pillar = Pillar::create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
        ]);
    }

    public function test_phase_one_submission_requires_all_five_documents_and_public_overview_locks_bast(): void
    {
        $incompletePayload = $this->phaseOnePayload('Program Tidak Lengkap');
        unset($incompletePayload['documents']['perjanjian_kerja_sama']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $incompletePayload)
            ->assertSessionHasErrors('documents.perjanjian_kerja_sama.file');

        $incompleteProgram = Program::where('nama_program', 'Program Tidak Lengkap')->firstOrFail();
        $this->assertSame('draft', $incompleteProgram->status);
        $this->assertCount(4, $incompleteProgram->documents);

        $program = $this->submitPhaseOne('Program Menunggu Persetujuan');

        $this->assertSame('pending_fase1', $program->status);
        $this->assertEqualsCanonicalizing(
            array_keys(Program::PHASE_ONE_DOCUMENTS),
            $program->documents()->pluck('document_type')->all(),
        );
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'title' => 'Pengajuan Program TJSL oleh '.$this->admin->name,
            'message' => 'Program "Program Menunggu Persetujuan" menunggu persetujuan Super Admin.',
            'related_type' => 'program',
            'related_id' => $program->id,
        ]);

        $detail = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program));
        $detail->assertOk()
            ->assertDontSee('Berita Acara Serah Terima (BAST)')
            ->assertDontSee(route('admin.programs.phase2', $program), false)
            ->assertDontSee('Fase 1')
            ->assertDontSee('name="dokumen_f"', false);

        $this->get(route('admin.programs.phase2', $program))
            ->assertForbidden();

        $overview = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'));
        $overview->assertOk()
            ->assertDontSee('Program Menunggu Persetujuan')
            ->assertSee('Program TJSL')
            ->assertDontSee('Eksternal')
            ->assertSee('background-color: #dc2626', false)
            ->assertSee('name="keyword"', false)
            ->assertDontSee('Dokumen Mandatory')
            ->assertDontSee('Dokumen Pelengkap');
    }

    public function test_rejected_phase_one_program_leaves_active_lists_and_public_overview(): void
    {
        $program = $this->submitPhaseOne('Program Uji Ditolak');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.reject', $program), [
                'rejected_reason' => 'Proposal dan kajian belum memenuhi ketentuan.',
            ])
            ->assertRedirect();

        $program->refresh();
        $this->assertSame('rejected_fase1', $program->status);
        $this->assertSame($this->superAdmin->id, $program->fase1_reviewed_by);
        $this->assertSame('Proposal dan kajian belum memenuhi ketentuan.', $program->fase1_rejected_reason);

        $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertDontSee('Program Uji Ditolak')
            ->assertDontSee('Proposal dan kajian belum memenuhi ketentuan.')
            ->assertDontSee('data-program-status="rejected_fase1"', false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertDontSee('Program Uji Ditolak');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.index'))
            ->assertOk()
            ->assertDontSee('Program Uji Ditolak');
    }

    public function test_approved_phase_one_can_submit_bast_and_complete_phase_two(): void
    {
        $program = $this->submitPhaseOne('Program Sampai Selesai');

        $phaseOneApproval = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.approve', $program))
            ->assertRedirect();
        $phaseOneApproval->assertSessionMissing('approval_completed');

        $program->refresh();
        $this->assertSame('approved_fase1', $program->status);
        $this->assertSame($this->superAdmin->id, $program->fase1_reviewed_by);

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', [$program, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Menunggu Admin mengunggah dokumen BAST.')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('class="review-edit"', false);

        $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee('data-document-column="F"', false)
            ->assertSee('data-document-state="in-progress"', false)
            ->assertSee('On Progress - menunggu upload dokumen BAST');

        $detail = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk();
        $detail
            ->assertSee(route('admin.programs.phase2', $program), false)
            ->assertSee('BAST &amp; Dokumentasi Tambahan', false)
            ->assertDontSee('Lanjut ke Tahap 2')
            ->assertDontSee('Berita Acara Serah Terima (BAST)')
            ->assertDontSee('name="dokumen_f"', false);

        $this->get(route('admin.programs.phase2', $program))
            ->assertOk()
            ->assertSee('BAST & Dokumentasi Tambahan', false)
            ->assertDontSee('Tahap 2')
            ->assertSee('name="bast_document_name"', false)
            ->assertSee('name="dokumen_f"', false)
            ->assertSee('name="phase2_photos[]"', false)
            ->assertSee('name="phase2_photo_captions[]"', false)
            ->assertSee('Tambah Gambar')
            ->assertSee('Simpan dan Ajukan BAST');

        $this->post(route('admin.programs.bast.store', $program), [
            'dokumen_f' => UploadedFile::fake()->create('bast-final.pdf', 100, 'application/pdf'),
            'bast_document_name' => 'BAST Final Program',
            'phase2_photo_captions' => [
                'Dokumentasi serah terima',
                'Foto tambahan kedua',
            ],
            'phase2_photos' => [
                UploadedFile::fake()->image('serah-terima.jpg', 1200, 800),
                UploadedFile::fake()->image('foto-kedua.jpg', 1200, 800),
            ],
        ])->assertRedirect(route('admin.programs.phase2', $program));

        $program->refresh();
        $this->assertSame('pending_fase2', $program->status);
        $this->assertSame('BAST Final Program', $program->documents()->where('document_type', 'bast')->firstOrFail()->nama_dokumen);

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', [$program, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Lihat BAST')
            ->assertSee('Approve BAST')
            ->assertSee('Reject BAST')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('class="review-edit"', false);

        $phaseTwoPhoto = $program->photos()->where('caption', 'Dokumentasi serah terima')->firstOrFail();
        Storage::disk('public')->assertExists($phaseTwoPhoto->file_path);
        $secondPhaseTwoPhoto = $program->photos()->where('caption', 'Foto tambahan kedua')->firstOrFail();
        Storage::disk('public')->assertExists($secondPhaseTwoPhoto->file_path);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'title' => 'Pengajuan Dokumen BAST Program TJSL oleh '.$this->admin->name,
            'message' => 'Dokumen BAST program "Program Sampai Selesai" menunggu persetujuan Super Admin.',
            'related_type' => 'program',
            'related_id' => $program->id,
        ]);

        $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee('data-document-column="F"', false)
            ->assertSee('data-document-state="complete"', false)
            ->assertDontSee('Menunggu Review');

        $finalApproval = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.approve', $program))
            ->assertRedirect();
        $finalApproval->assertSessionHas(
            'approval_completed',
            fn (array $payload): bool => $payload['type'] === 'internal'
                && $payload['name'] === 'Program Sampai Selesai',
        );

        $program->refresh();
        $this->assertSame('completed', $program->status);
        $this->assertSame($this->superAdmin->id, $program->fase2_reviewed_by);
        $this->assertNotNull($program->fase2_reviewed_at);

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', $program))
            ->assertOk()
            ->assertSee('data-approval-success-modal', false)
            ->assertSee('data-approval-type="internal"', false)
            ->assertSee('images/superadmin/approval-success-icon.png', false)
            ->assertSee('images/superadmin/approval-success-message.png', false);

        $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee('data-document-column="F"', false)
            ->assertSee('data-document-state="complete"', false);
    }

    public function test_rejected_bast_returns_to_approved_phase_one_and_can_be_reuploaded_without_repeating_phase_one(): void
    {
        $program = $this->submitPhaseOne('Program BAST Diulang');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.approve', $program))
            ->assertRedirect();
        $faseOneReviewer = $program->fresh()->fase1_reviewed_by;

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.bast.store', $program), [
                'dokumen_f' => UploadedFile::fake()->create('bast-salah.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.reject', $program), [
                'rejected_reason' => 'Tanda tangan pada BAST belum lengkap.',
            ])
            ->assertRedirect();

        $program->refresh();
        $this->assertSame('approved_fase1', $program->status);
        $this->assertSame($faseOneReviewer, $program->fase1_reviewed_by);
        $this->assertSame('Tanda tangan pada BAST belum lengkap.', $program->fase2_rejected_reason);
        $this->assertCount(5, $program->documents()->whereIn('document_type', array_keys(Program::PHASE_ONE_DOCUMENTS))->get());

        $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee('Program BAST Diulang')
            ->assertSee('data-document-state="in-progress"', false)
            ->assertSee('On Progress - menunggu perbaikan dan unggah ulang dokumen BAST');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee(route('admin.programs.phase2', $program), false)
            ->assertDontSee('Alasan BAST ditolak')
            ->assertDontSee('name="dokumen_f"', false);

        $this->get(route('admin.programs.phase2', $program))
            ->assertOk()
            ->assertSee('Alasan BAST ditolak')
            ->assertSee('Simpan Perbaikan dan Ajukan BAST');

        $this->post(route('admin.programs.bast.store', $program), [
            'dokumen_f' => UploadedFile::fake()->create('bast-perbaikan.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $program->refresh();
        $this->assertSame('pending_fase2', $program->status);
        $this->assertNull($program->fase2_rejected_reason);
        $this->assertSame(1, $program->documents()->where('document_type', 'bast')->count());
        $this->assertSame('bast-perbaikan.pdf', $program->documents()->where('document_type', 'bast')->first()->nama_dokumen);
    }

    private function submitPhaseOne(string $name): Program
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->phaseOnePayload($name))
            ->assertSessionHasNoErrors();

        return Program::where('nama_program', $name)->firstOrFail();
    }

    private function phaseOnePayload(string $name): array
    {
        return [
            'pillar_id' => $this->pillar->id,
            'nama_program' => $name,
            'deskripsi_program' => 'Deskripsi program',
            'sasaran_program' => 'Sasaran program',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra program',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'tujuan_program' => 'Tujuan program',
            'documents' => collect(Program::PHASE_ONE_DOCUMENTS)
                ->mapWithKeys(fn ($label, $type) => [
                    $type => [
                        'nama' => $label,
                        'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
                    ],
                ])
                ->all(),
            'action' => 'submit',
        ];
    }
}
