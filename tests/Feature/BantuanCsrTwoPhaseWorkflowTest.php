<?php

namespace Tests\Feature;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BantuanCsrTwoPhaseWorkflowTest extends TestCase
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

        foreach ([
            ['Sosial', 'sosial', '#2563eb'],
            ['Ekonomi', 'ekonomi', '#f59e0b'],
            ['Lingkungan', 'lingkungan', '#16a34a'],
            ['Hukum & Tata Kelola', 'hukum-tata-kelola', '#dc2626'],
        ] as [$name, $slug, $colorHex]) {
            $pillar = Pillar::create([
                'name' => $name,
                'slug' => $slug,
                'color_hex' => $colorHex,
            ]);

            if ($slug === 'sosial') {
                $this->pillar = $pillar;
            }
        }
    }

    public function test_create_page_removes_program_relation_and_only_shows_non_pks_initial_documents(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.create'));

        $response->assertOk()
            ->assertSee('Bantuan TJSL INKA')
            ->assertDontSee('name="program_id"', false)
            ->assertSee('name="pillar_id"', false)
            ->assertSee('Sosial')
            ->assertSee('Ekonomi')
            ->assertSee('Lingkungan')
            ->assertSee('Hukum &amp; Tata Kelola', false)
            ->assertSee('name="documents[A][file]"', false)
            ->assertSee('Proposal Pengajuan Program')
            ->assertSee('name="documents[B][file]"', false)
            ->assertSee('Kelengkapan Survei')
            ->assertDontSee('name="documents[proposal_permintaan][file]"', false)
            ->assertDontSee('Proposal Permintaan Bantuan')
            ->assertDontSee('name="documents[formulir_kajian_proposal][file]"', false)
            ->assertDontSee('Formulir Kajian Proposal')
            ->assertDontSee('name="documents[formulir_survei][file]"', false)
            ->assertDontSee('Formulir Survei Calon Penerima Bantuan CSR')
            ->assertDontSee('name="documents[laporan_hasil_survei][file]"', false)
            ->assertDontSee('Laporan Hasil Survei Calon Penerima Bantuan CSR')
            ->assertDontSee('name="documents[formulir_persetujuan][file]"', false)
            ->assertDontSee('Formulir Persetujuan Bantuan')
            ->assertDontSee('name="documents[dokumentasi_survei][file]"', false)
            ->assertDontSee('Dokumentasi Survei Calon Penerima Bantuan CSR')
            ->assertDontSee('name="dokumen_f"', false)
            ->assertDontSee('Berita Acara Serah Terima (BAST)');

        $this->assertSame(
            ['A', 'B'],
            array_keys(BantuanCsr::PHASE_ONE_DOCUMENTS),
        );
    }

    public function test_phase_one_submission_requires_both_initial_documents_and_ignores_legacy_document_types(): void
    {
        $incompletePayload = $this->phaseOnePayload('CSR Dokumen Kurang');
        unset($incompletePayload['documents']['B']);
        $incompletePayload['documents']['formulir_kajian_proposal'] = [
            'nama' => 'Dokumen lama tidak berlaku',
            'file' => UploadedFile::fake()->create(
                'formulir-kajian-lama.pdf',
                100,
                'application/pdf',
            ),
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.assistance.store'), $incompletePayload)
            ->assertSessionHasErrors('documents.B.file');

        $incomplete = BantuanCsr::where(
            'nama_program_bantuan',
            'CSR Dokumen Kurang',
        )->firstOrFail();

        $this->assertSame('draft', $incomplete->status);
        $this->assertEqualsCanonicalizing(
            ['A'],
            $incomplete->documents()->pluck('document_type')->all(),
        );

        $payload = $this->phaseOnePayload('CSR Menunggu Persetujuan');
        $payload['documents']['proposal_permintaan'] = [
            'nama' => 'Proposal lama tidak berlaku',
            'file' => UploadedFile::fake()->create(
                'proposal-lama.pdf',
                100,
                'application/pdf',
            ),
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.assistance.store'), $payload)
            ->assertSessionHasNoErrors();

        $item = BantuanCsr::where(
            'nama_program_bantuan',
            'CSR Menunggu Persetujuan',
        )->firstOrFail();

        $this->assertSame('pending_fase1', $item->status);
        $this->assertNotNull($item->submitted_at);
        $this->assertEqualsCanonicalizing(
            ['A', 'B'],
            $item->documents()->pluck('document_type')->all(),
        );
        $this->assertSame($this->pillar->id, $item->pillar_id);
        $this->assertDatabaseMissing('bantuan_csr_documents', [
            'bantuan_csr_id' => $item->id,
            'document_type' => 'proposal_permintaan',
        ]);
    }

    public function test_bast_is_reviewed_separately_and_can_be_reuploaded_after_rejection(): void
    {
        $item = $this->submitPhaseOne('CSR Sampai Selesai');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.phase2', $item))
            ->assertForbidden();

        $phaseOneApproval = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.assistance.approve', $item))
            ->assertRedirect();
        $phaseOneApproval->assertSessionMissing('approval_completed');

        $item->refresh();
        $this->assertSame('approved_fase1', $item->status);
        $this->assertSame($this->superAdmin->id, $item->fase1_reviewed_by);
        $this->assertNotNull($item->fase1_reviewed_at);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.phase2', $item))
            ->assertOk()
            ->assertSee('Berita Acara Serah Terima (BAST)')
            ->assertSee('name="dokumen_f"', false)
            ->assertDontSee('name="documents[A][file]"', false)
            ->assertDontSee('name="documents[B][file]"', false);

        $this->post(route('admin.assistance.bast.store', $item), [
            'dokumen_f' => UploadedFile::fake()->create(
                'bast-belum-benar.pdf',
                100,
                'application/pdf',
            ),
            'bast_document_name' => 'BAST Belum Benar',
        ])->assertRedirect(route('admin.assistance.phase2', $item));

        $item->refresh();
        $this->assertSame('pending_fase2', $item->status);
        $this->assertSame(
            'BAST Belum Benar',
            $item->documents()
                ->where('document_type', 'bast')
                ->firstOrFail()
                ->nama_dokumen,
        );

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.assistance.reject', $item), [
                'rejected_reason' => 'Tanda tangan BAST belum lengkap.',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('approved_fase1', $item->status);
        $this->assertSame(
            'Tanda tangan BAST belum lengkap.',
            $item->fase2_rejected_reason,
        );
        $this->assertSame($this->superAdmin->id, $item->fase2_reviewed_by);
        $this->assertNotNull($item->fase2_reviewed_at);
        $this->assertEqualsCanonicalizing(
            ['A', 'B'],
            $item->documents()
                ->whereIn(
                    'document_type',
                    array_keys(BantuanCsr::PHASE_ONE_DOCUMENTS),
                )
                ->pluck('document_type')
                ->all(),
        );

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.phase2', $item))
            ->assertOk()
            ->assertSee('Tanda tangan BAST belum lengkap.')
            ->assertSee('name="dokumen_f"', false);

        $this->post(route('admin.assistance.bast.store', $item), [
            'dokumen_f' => UploadedFile::fake()->create(
                'bast-perbaikan.pdf',
                100,
                'application/pdf',
            ),
            'bast_document_name' => 'BAST Perbaikan',
        ])->assertRedirect(route('admin.assistance.phase2', $item));

        $item->refresh();
        $this->assertSame('pending_fase2', $item->status);
        $this->assertNull($item->fase2_rejected_reason);
        $this->assertSame(
            1,
            $item->documents()->where('document_type', 'bast')->count(),
        );
        $this->assertSame(
            'BAST Perbaikan',
            $item->documents()
                ->where('document_type', 'bast')
                ->firstOrFail()
                ->nama_dokumen,
        );

        $finalApproval = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.assistance.approve', $item))
            ->assertRedirect();
        $finalApproval->assertSessionHas(
            'approval_completed',
            fn (array $payload): bool => $payload['type'] === 'csr'
                && $payload['name'] === 'CSR Sampai Selesai',
        );

        $item->refresh();
        $this->assertSame('completed', $item->status);
        $this->assertSame($this->superAdmin->id, $item->fase2_reviewed_by);
        $this->assertNotNull($item->fase2_reviewed_at);
    }

    public function test_phase_one_rejection_is_terminal_and_blocks_edit_resubmission_and_bast(): void
    {
        $item = $this->submitPhaseOne('CSR Ditolak Final');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.assistance.reject', $item), [
                'rejected_reason' => 'Dokumen awal belum memenuhi persyaratan.',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('rejected_fase1', $item->status);
        $this->assertFalse($item->canBeEdited());

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.show', $item))
            ->assertOk()
            ->assertSee('Pengajuan dihentikan')
            ->assertDontSee('Simpan dan Ajukan Kepada Super Admin');

        $this->put(route('admin.assistance.update', $item), [])
            ->assertForbidden();

        $this->get(route('admin.assistance.phase2', $item))
            ->assertForbidden();

        $this->get(route('admin.assistance.index'))
            ->assertOk()
            ->assertDontSee('CSR Ditolak Final');
    }

    public function test_bast_page_accepts_documentation_photos_and_superadmin_can_review_them(): void
    {
        $item = $this->submitPhaseOne('CSR dengan Foto Dokumentasi');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.assistance.approve', $item))
            ->assertRedirect();

        $this->get(route('superadmin.assistance.show', [$item, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Belum ada dokumen BAST.')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('class="review-edit"', false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.phase2', $item))
            ->assertOk()
            ->assertSee('Dokumentasi Bantuan')
            ->assertSee('Tambah Gambar')
            ->assertSee('name="phase2_photo_captions[]"', false)
            ->assertSee('name="phase2_photos[]"', false)
            ->assertDontSee('name="additional_documents', false);

        $this->post(route('admin.assistance.bast.store', $item), [
            'dokumen_f' => UploadedFile::fake()->create(
                'bast-dengan-foto.pdf',
                100,
                'application/pdf',
            ),
            'bast_document_name' => 'BAST dengan Foto',
            'phase2_photo_captions' => [
                'Penyerahan bantuan kepada penerima',
                '',
            ],
            'phase2_photos' => [
                UploadedFile::fake()->image('penyerahan.jpg', 1200, 800),
                UploadedFile::fake()->image('penerima.png', 1200, 800),
            ],
        ])->assertRedirect(route('admin.assistance.phase2', $item))
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('pending_fase2', $item->status);
        $this->assertSame(
            1,
            $item->documents()->where('document_type', 'bast')->count(),
        );

        $photos = $item->photos()->orderBy('order')->get();
        $this->assertCount(2, $photos);
        $this->assertSame(
            [
                'Penyerahan bantuan kepada penerima',
                null,
            ],
            $photos->pluck('caption')->all(),
        );
        $photos->each(
            fn ($photo) => Storage::disk('public')
                ->assertExists($photo->file_path),
        );
        $this->assertFalse(
            $item->documents()
                ->where(
                    'document_type',
                    BantuanCsr::PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE,
                )
                ->exists(),
        );

        $superAdminResponse = $this
            ->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.assistance.show', $item));

        $superAdminResponse->assertOk()
            ->assertSee('Dokumentasi Bantuan')
            ->assertSee('Penyerahan bantuan kepada penerima')
            ->assertSee('Tanpa keterangan')
            ->assertSee(Storage::url($photos->first()->file_path), false)
            ->assertSee('Lihat BAST')
            ->assertSee('Setujui BAST')
            ->assertSee('Tolak BAST')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('class="review-edit"', false);

        $adminResponse = $this
            ->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.phase2', $item));

        $adminResponse->assertOk()
            ->assertSee('Dokumentasi Bantuan Saat Ini')
            ->assertSee('Penyerahan bantuan kepada penerima')
            ->assertSee(Storage::url($photos->last()->file_path), false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.show', $item))
            ->assertOk()
            ->assertSee('Dokumentasi Bantuan')
            ->assertSee(Storage::url($photos->first()->file_path), false);
    }

    public function test_bast_documentation_rejects_non_image_and_oversized_files(): void
    {
        $item = $this->submitPhaseOne('CSR Foto Tidak Valid');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.assistance.approve', $item))
            ->assertRedirect();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.assistance.bast.store', $item), [
                'dokumen_f' => UploadedFile::fake()->create(
                    'bast-valid.pdf',
                    100,
                    'application/pdf',
                ),
                'phase2_photos' => [
                    UploadedFile::fake()->create(
                        'bukan-foto.pdf',
                        100,
                        'application/pdf',
                    ),
                    UploadedFile::fake()
                        ->image('foto-terlalu-besar.jpg')
                        ->size(20481),
                ],
            ])
            ->assertSessionHasErrors([
                'phase2_photos.0',
                'phase2_photos.1',
            ]);

        $item->refresh();
        $this->assertSame('approved_fase1', $item->status);
        $this->assertFalse(
            $item->documents()->where('document_type', 'bast')->exists(),
        );
        $this->assertFalse($item->photos()->exists());
    }

    public function test_bantuan_csr_archive_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('superadmin.assistance.destroy'));
        $this->assertFalse(Route::has('superadmin.assistance.archive'));
        $this->assertFalse(Route::has('admin.assistance.archive'));

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get('/superadmin/bantuan-csr/arsip')
            ->assertNotFound();
    }

    public function test_super_admin_can_only_review_and_cannot_mutate_bantuan_content(): void
    {
        $item = $this->submitPhaseOne('CSR Read Only Super Admin');
        $document = $item->documents()->firstOrFail();

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.assistance.show', [$item, 'edit' => 1]));

        $response->assertOk()
            ->assertSee('readonly', false)
            ->assertSee('Setujui Dokumen Awal')
            ->assertSee('Tolak Dokumen Awal')
            ->assertDontSee('class="review-edit"', false)
            ->assertDontSee('Simpan Bantuan CSR')
            ->assertDontSee('type="file"', false);

        $this->put('/superadmin/bantuan-csr/'.$item->id, [
            'nama_program_bantuan' => 'Nama yang tidak boleh tersimpan',
        ])->assertStatus(405);

        $this->delete('/superadmin/bantuan-documents/'.$document->id)
            ->assertStatus(405);

        $this->post('/superadmin/bantuan-csr/'.$item->id.'/bast')
            ->assertNotFound();

        $this->assertSame('CSR Read Only Super Admin', $item->fresh()->nama_program_bantuan);
        $this->assertSame('pending_fase1', $item->fresh()->status);
        $this->assertDatabaseHas('bantuan_csr_documents', ['id' => $document->id]);
    }

    private function submitPhaseOne(string $name): BantuanCsr
    {
        $this->actingAs($this->admin, 'admin')
            ->post(
                route('admin.assistance.store'),
                $this->phaseOnePayload($name),
            )
            ->assertSessionHasNoErrors();

        return BantuanCsr::where('nama_program_bantuan', $name)
            ->firstOrFail();
    }

    private function phaseOnePayload(string $name): array
    {
        return [
            'nama_program_bantuan' => $name,
            'deskripsi_bantuan' => 'Deskripsi bantuan CSR',
            'pillar_id' => $this->pillar->id,
            'rencana_anggaran' => 5000000,
            'realisasi_anggaran' => 1000000,
            'targets' => ['Membantu penerima manfaat'],
            'details' => [[
                'rincian_kegiatan' => 'Penyaluran bantuan',
                'penerima_bantuan' => 'Masyarakat',
                'jenis_bantuan' => 'Barang',
                'quality' => '10 unit',
                'nominal_bantuan' => 1000000,
            ]],
            'documents' => collect(BantuanCsr::PHASE_ONE_DOCUMENTS)
                ->mapWithKeys(fn ($label, $type) => [
                    $type => [
                        'nama' => $label,
                        'file' => UploadedFile::fake()->create(
                            "{$type}.pdf",
                            100,
                            'application/pdf',
                        ),
                    ],
                ])
                ->all(),
            'action' => 'submit',
        ];
    }
}
