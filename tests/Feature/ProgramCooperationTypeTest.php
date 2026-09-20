<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramCooperationTypeTest extends TestCase
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

    public function test_create_page_branches_directly_to_pks_and_non_pks_forms(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.create'))
            ->assertOk()
            ->assertSee('Pilih Jenis Kerja Sama Program TJSL')
            ->assertSee('PKS')
            ->assertSee('NON-PKS')
            ->assertSee(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'pks']), false)
            ->assertSee(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'non-pks']), false)
            ->assertDontSee('Program Eksternal');

        $this->get(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'pks']))
            ->assertOk()
            ->assertSee('value="pks"', false)
            ->assertSee('name="tujuan_program"', false)
            ->assertDontSee('name="tujuan[', false)
            ->assertDontSee('Tambah Tujuan Program')
            ->assertSee('Proposal Pengajuan Program')
            ->assertSee('Kelengkapan Survei')
            ->assertSee('Kajian Kelayakan Kerja Sama')
            ->assertSee('Kajian Risiko')
            ->assertSee('Perjanjian Kerja Sama')
            ->assertDontSee('name="dokumen_f"', false);

        $this->get(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'non-pks']))
            ->assertOk()
            ->assertSee('value="non_pks"', false)
            ->assertSee('name="tujuan_program"', false)
            ->assertDontSee('name="tujuan[', false)
            ->assertDontSee('Tambah Tujuan Program')
            ->assertSee('Proposal Pengajuan Program')
            ->assertSee('Kelengkapan Survei')
            ->assertDontSee('Kajian Kelayakan Kerja Sama')
            ->assertDontSee('Kajian Risiko')
            ->assertDontSee('Perjanjian Kerja Sama')
            ->assertDontSee('name="dokumen_f"', false);
    }

    public function test_removed_program_type_routes_are_not_available(): void
    {
        foreach ([
            'admin.programs.internal.create',
            'admin.programs.internal.form',
            'admin.external-programs.create',
            'admin.external-programs.store',
            'admin.external-programs.show',
            'admin.external-programs.update',
            'superadmin.external-programs.show',
            'superadmin.external-programs.stage2.continue',
            'superadmin.external-programs.stage2.reject',
            'superadmin.external-programs.stage3.complete',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route lama {$routeName} masih terdaftar.");
        }

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/program-tjsl/create/eksternal')
            ->assertNotFound();

        $this->get('/admin/program-eksternal/1')->assertNotFound();

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get('/superadmin/program-eksternal/1')
            ->assertNotFound();
    }

    public function test_non_pks_submission_requires_only_a_and_b_and_ignores_c_d_e_uploads(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload(
                name: 'Program Internal NON-PKS',
                cooperationType: 'non_pks',
                documentTypes: [
                    'proposal_pengajuan_program',
                    'kelengkapan_survei',
                    'kajian_kelayakan',
                ],
            ));

        $program = Program::where('nama_program', 'Program Internal NON-PKS')->firstOrFail();

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.programs.show', $program));
        $this->assertSame('non_pks', $program->jenis_kerjasama);
        $this->assertSame('pending_fase1', $program->status);
        $this->assertEqualsCanonicalizing(
            ['proposal_pengajuan_program', 'kelengkapan_survei'],
            $program->documents()->pluck('document_type')->all(),
        );

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.approve', $program))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved_fase1', $program->fresh()->status);
    }

    public function test_public_overview_shows_cooperation_badges_and_non_pks_na_columns(): void
    {
        $pks = $this->createProgram('Program PKS Overview', 'pks');
        $nonPks = $this->createProgram('Program NON-PKS Overview', 'non_pks');

        foreach (array_keys(Program::PHASE_ONE_DOCUMENTS) as $type) {
            $pks->documents()->create([
                'document_type' => $type,
                'nama_dokumen' => "{$type}.pdf",
                'file_path' => "programs/{$pks->id}/documents/{$type}.pdf",
                'uploaded_at' => now(),
            ]);
        }
        foreach (array_keys($nonPks->phaseOneDocuments()) as $type) {
            $nonPks->documents()->create([
                'document_type' => $type,
                'nama_dokumen' => "{$type}.pdf",
                'file_path' => "programs/{$nonPks->id}/documents/{$type}.pdf",
                'uploaded_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee('data-cooperation-kind="pks"', false)
            ->assertSee('data-cooperation-kind="non_pks"', false)
            ->assertSee('Program PKS Overview')
            ->assertSee('Program NON-PKS Overview');

        $pksRow = $this->programRow($response->getContent(), $pks->id);
        $nonPksRow = $this->programRow($response->getContent(), $nonPks->id);

        $this->assertStringContainsString('>PKS<', preg_replace('/\s+/', '', $pksRow));
        $this->assertStringContainsString('>NON-PKS<', preg_replace('/\s+/', '', $nonPksRow));
        $this->assertSame(0, substr_count($pksRow, 'data-document-state="na"'));
        $this->assertSame(3, substr_count($nonPksRow, 'data-document-state="na"'));

        foreach (['C', 'D', 'E'] as $column) {
            $this->assertMatchesRegularExpression(
                '/data-document-column="'.$column.'"[^>]*data-document-state="na"/s',
                $nonPksRow,
            );
        }
    }

    private function createProgram(string $name, string $cooperationType): Program
    {
        return Program::create([
            'slug' => str($name)->slug(),
            'pillar_id' => $this->pillar->id,
            'jenis_kerjasama' => $cooperationType,
            'nama_program' => $name,
            'deskripsi_program' => 'Deskripsi program',
            'sasaran_program' => 'Sasaran program',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra program',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'tujuan_program' => 'Tujuan program',
            'status' => 'approved_fase1',
            'created_by' => $this->admin->id,
        ]);
    }

    private function programPayload(string $name, string $cooperationType, array $documentTypes): array
    {
        return [
            'pillar_id' => $this->pillar->id,
            'jenis_kerjasama' => $cooperationType,
            'nama_program' => $name,
            'deskripsi_program' => 'Deskripsi program',
            'sasaran_program' => 'Sasaran program',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra program',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'tujuan_program' => 'Tujuan program',
            'documents' => collect($documentTypes)
                ->mapWithKeys(fn (string $type) => [
                    $type => [
                        'nama' => Program::PHASE_ONE_DOCUMENTS[$type],
                        'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
                    ],
                ])
                ->all(),
            'action' => 'submit',
        ];
    }

    private function programRow(string $html, int $programId): string
    {
        $matched = preg_match(
            '/<tr\b[^>]*data-program-id="'.$programId.'".*?<\/tr>/s',
            $html,
            $matches,
        );

        $this->assertSame(1, $matched, "Baris program {$programId} tidak ditemukan.");

        return $matches[0];
    }
}
