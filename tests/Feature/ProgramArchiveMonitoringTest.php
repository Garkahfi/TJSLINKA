<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProgramArchiveMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    private User $superAdmin;

    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'Admin Utama');
        $this->otherAdmin = $this->user('admin', 'Admin Lain');
        $this->superAdmin = $this->user('super_admin', 'Super Admin');
        $this->pillar = Pillar::create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
        ]);
    }

    public function test_public_overview_only_shows_programs_approved_by_super_admin_and_tracks_bast_progress(): void
    {
        $pendingInitial = $this->internalProgram(
            $this->admin,
            'Internal Menunggu Pemeriksaan Awal',
            'pending_fase1',
            'pks',
        );
        $rejectedInitial = $this->internalProgram(
            $this->admin,
            'Internal Ditolak',
            'rejected_fase1',
            'pks',
            rejectedReason: 'Dokumen awal belum layak.',
        );
        $approvedWithoutBast = $this->internalProgram(
            $this->admin,
            'Internal Sudah ACC Menunggu BAST',
            'approved_fase1',
            'pks',
        );
        $this->addDocuments($approvedWithoutBast, [
            'proposal_pengajuan_program',
            'kelengkapan_survei',
            'kajian_kelayakan',
            'kajian_mitigasi_risiko',
            'perjanjian_kerja_sama',
        ]);

        $approvedAfterBastRejection = $this->internalProgram(
            $this->admin,
            'Internal Perbaikan BAST',
            'approved_fase1',
            'pks',
        );
        $approvedAfterBastRejection->update([
            'fase2_rejected_reason' => 'BAST perlu diperbaiki.',
            'fase2_reviewed_by' => $this->superAdmin->id,
            'fase2_reviewed_at' => now(),
        ]);
        // File lama tetap tersimpan setelah penolakan BAST, tetapi state F harus
        // tetap On Progress sampai Admin mengunggah ulang.
        $this->addDocuments($approvedAfterBastRejection, ['bast']);

        $pendingBastReview = $this->internalProgram(
            $this->otherAdmin,
            'Internal BAST Menunggu Review',
            'pending_fase2',
            'non_pks',
        );
        $this->addDocuments($pendingBastReview, [
            'proposal_pengajuan_program',
            'kelengkapan_survei',
            'bast',
        ]);

        $completed = $this->internalProgram(
            $this->otherAdmin,
            'Internal Selesai',
            'completed',
            'pks',
        );
        $this->addDocuments($completed, ['bast']);

        $internalDraft = $this->internalProgram(
            $this->admin,
            'Draft Internal Privat',
            'draft',
            'pks',
        );
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'));

        $response->assertOk()
            ->assertSee('Overview Program TJSL INKA')
            ->assertSee('Kelengkapan Dokumen')
            ->assertSee('name="keyword"', false)
            ->assertSee('Internal Sudah ACC Menunggu BAST')
            ->assertSee('Internal Perbaikan BAST')
            ->assertSee('Internal BAST Menunggu Review')
            ->assertSee('Internal Selesai')
            ->assertDontSee('Internal Menunggu Pemeriksaan Awal')
            ->assertDontSee('Internal Ditolak')
            ->assertDontSee('Draft Internal Privat');

        $this->assertProgramRow(
            $response,
            $approvedWithoutBast,
            'internal',
            ['F' => 'in-progress'],
            'pks',
            ['Program TJSL', 'PKS'],
        );
        $this->assertProgramRow(
            $response,
            $approvedAfterBastRejection,
            'internal',
            ['F' => 'in-progress'],
            'pks',
        );
        $this->assertProgramRow(
            $response,
            $pendingBastReview,
            'internal',
            [
                'A' => 'complete',
                'B' => 'complete',
                'C' => 'na',
                'D' => 'na',
                'E' => 'na',
                'F' => 'complete',
            ],
            'non_pks',
        );
        $this->assertProgramRow($response, $completed, 'internal', ['F' => 'complete'], 'pks');
        foreach ([$pendingInitial, $rejectedInitial, $internalDraft] as $hiddenInternal) {
            $this->assertProgramRowAbsent($response, $hiddenInternal, 'internal');
        }
    }

    public function test_all_dashboard_archive_features_are_removed_while_monitoring_keeps_legacy_data(): void
    {
        foreach ([
            'admin.programs.archive',
            'superadmin.programs.archive',
            'admin.assistance.archive',
            'superadmin.assistance.archive',
            'superadmin.programs.destroy',
            'superadmin.assistance.destroy',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName));
        }

        $this->actingAs($this->admin, 'admin');
        $this->get('/admin/program-tjsl/arsip')->assertNotFound();
        $this->get('/admin/bantuan-csr/arsip')->assertNotFound();
        $adminSidebar = $this->get(route('admin.home'));
        $adminSidebar->assertOk()
            ->assertDontSee('Arsip Program TJSL')
            ->assertDontSee('Arsip Bantuan CSR');

        $this->actingAs($this->superAdmin, 'superadmin');
        $this->get('/superadmin/program-tjsl/arsip')->assertNotFound();
        $this->get('/superadmin/bantuan-csr/arsip')->assertNotFound();
        $superAdminSidebar = $this->get(route('superadmin.home'));
        $superAdminSidebar->assertOk()
            ->assertDontSee('Arsip Program TJSL')
            ->assertDontSee('Arsip Bantuan CSR');

        $legacyArchived = $this->internalProgram(
            $this->admin,
            'Program Arsip Lama Tetap Termonitor',
            'completed',
            'pks',
            archived: true,
        );

        $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee($legacyArchived->nama_program);
    }

    public function test_public_overview_searches_approved_programs_without_exposing_unapproved_records(): void
    {
        $matchingInternal = $this->internalProgram(
            $this->admin,
            'Pemberdayaan Batik Madiun',
            'approved_fase1',
            'pks',
        );
        $otherInternal = $this->internalProgram(
            $this->admin,
            'Penghijauan Area Operasional',
            'completed',
            'pks',
        );
        $unapprovedMatch = $this->internalProgram(
            $this->admin,
            'Batik Masih Menunggu Review',
            'pending_fase1',
            'pks',
        );
        $internalResponse = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview', ['keyword' => 'Batik']));

        $internalResponse->assertOk()
            ->assertSee($matchingInternal->nama_program)
            ->assertDontSee($otherInternal->nama_program)
            ->assertDontSee($unapprovedMatch->nama_program)
            ->assertSee('name="keyword"', false)
            ->assertSee('value="Batik"', false)
            ->assertSee('keyword=Batik', false);

        $this->assertProgramRow($internalResponse, $matchingInternal, 'internal');
        $this->assertProgramRowAbsent($internalResponse, $unapprovedMatch, 'internal');

        $this->get(route('program.overview', ['keyword' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('keyword');
    }

    public function test_approved_monitoring_can_be_filtered_by_review_year(): void
    {
        $reviewedIn2025 = Carbon::create(2025, 8, 17, 9, 15, 0);
        $reviewedIn2026 = Carbon::create(2026, 2, 4, 14, 30, 0);

        $internal2025 = $this->internalProgram(
            $this->admin,
            'Internal Disetujui Tahun 2025',
            'approved_fase1',
            'pks',
        );
        $internal2025->update([
            'fase1_reviewed_by' => $this->superAdmin->id,
            'fase1_reviewed_at' => $reviewedIn2025,
        ]);
        $internal2026 = $this->internalProgram(
            $this->admin,
            'Program Disetujui Tahun 2026',
            'approved_fase1',
            'pks',
        );
        $internal2026->update([
            'fase1_reviewed_by' => $this->superAdmin->id,
            'fase1_reviewed_at' => $reviewedIn2026,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview', ['year' => 2025]));

        $response->assertOk()
            ->assertSee('Internal Disetujui Tahun 2025')
            ->assertDontSee('Program Disetujui Tahun 2026');

        $this->assertProgramRow($response, $internal2025, 'internal');
        $this->assertProgramRowAbsent($response, $internal2026, 'internal');

        $document = $this->responseDocument($response);
        $xpath = new DOMXPath($document);
        $yearControls = $xpath->query('//*[@data-monitoring-year="2025"]');

        $this->assertGreaterThanOrEqual(
            1,
            $yearControls?->length ?? 0,
            'Filter monitoring tahun 2025 harus dirender dengan data-monitoring-year="2025".',
        );
    }

    /**
     * @param  array<string, string>  $expectedStates
     * @param  list<string>  $expectedText
     */
    private function assertProgramRow(
        TestResponse $response,
        Program $program,
        string $kind,
        array $expectedStates = [],
        ?string $cooperation = null,
        array $expectedText = [],
    ): void {
        $row = $this->programRow($response, $program, $kind);

        $this->assertInstanceOf(
            DOMElement::class,
            $row,
            "Baris monitoring {$kind} #{$program->getKey()} tidak ditemukan.",
        );

        if ($cooperation !== null) {
            $this->assertSame(
                $cooperation,
                $row->getAttribute('data-cooperation-kind'),
                "Jenis kerja sama baris {$kind} #{$program->getKey()} tidak sesuai.",
            );
        }

        foreach ($expectedText as $text) {
            $this->assertStringContainsString(
                $text,
                $row->textContent,
                "Teks [{$text}] tidak ditemukan pada baris {$kind} #{$program->getKey()}.",
            );
        }

        $xpath = new DOMXPath($row->ownerDocument);

        foreach ($expectedStates as $column => $state) {
            $cells = $xpath->query(
                './/*[@data-document-column="'.$column.'" and @data-document-state="'.$state.'"]',
                $row,
            );

            $this->assertSame(
                1,
                $cells?->length,
                "Kolom {$column} pada baris {$kind} #{$program->getKey()} seharusnya berstatus {$state}.",
            );
        }
    }

    private function assertProgramRowAbsent(
        TestResponse $response,
        Program $program,
        string $kind,
    ): void {
        $this->assertNull(
            $this->programRow($response, $program, $kind),
            "Baris {$kind} #{$program->getKey()} seharusnya tidak muncul.",
        );
    }

    private function assertRejectionDialog(
        TestResponse $response,
        Program $program,
        string $kind,
        string $reason,
        string $reviewer,
        string $reviewedAt,
    ): void {
        $document = $this->responseDocument($response);
        $xpath = new DOMXPath($document);
        $row = $this->findProgramRow($document, $program, $kind);

        $this->assertInstanceOf(
            DOMElement::class,
            $row,
            "Baris monitoring {$kind} #{$program->getKey()} tidak ditemukan.",
        );

        $buttons = $xpath->query('.//button[@data-rejection-open]', $row);

        $this->assertSame(
            1,
            $buttons?->length,
            "Baris {$kind} #{$program->getKey()} harus mempunyai satu tombol ikon alasan penolakan.",
        );

        $button = $buttons?->item(0);
        $this->assertInstanceOf(DOMElement::class, $button);
        $dialogTarget = $button->getAttribute('data-rejection-open');

        $this->assertNotSame(
            '',
            $dialogTarget,
            'Tombol alasan penolakan harus menunjuk dialog lewat data-rejection-open.',
        );
        $this->assertStringContainsString(
            'alasan',
            mb_strtolower($button->getAttribute('aria-label')),
            'Tombol ikon alasan penolakan harus memiliki label aksesibel.',
        );

        $dialogs = $xpath->query(
            '//*[@data-rejection-dialog="'.$dialogTarget.'"]',
        );

        $this->assertSame(
            1,
            $dialogs?->length,
            "Dialog alasan [{$dialogTarget}] untuk {$kind} #{$program->getKey()} tidak ditemukan.",
        );

        $dialog = $dialogs?->item(0);
        $this->assertInstanceOf(DOMElement::class, $dialog);

        foreach ([$reason, $reviewer, $reviewedAt] as $text) {
            $this->assertStringContainsString(
                $text,
                $dialog->textContent,
                "Dialog alasan {$kind} #{$program->getKey()} tidak memuat [{$text}].",
            );
        }
    }

    private function programRow(
        TestResponse $response,
        Program $program,
        string $kind,
    ): ?DOMElement {
        return $this->findProgramRow(
            $this->responseDocument($response),
            $program,
            $kind,
        );
    }

    private function findProgramRow(
        DOMDocument $document,
        Program $program,
        string $kind,
    ): ?DOMElement {
        $xpath = new DOMXPath($document);
        $rows = $xpath->query(
            '//*[@data-program-id="'.$program->getKey().'" and @data-program-kind="'.$kind.'"]',
        );
        $row = $rows?->item(0);

        return $row instanceof DOMElement ? $row : null;
    }

    private function responseDocument(TestResponse $response): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function internalProgram(
        User $creator,
        string $name,
        string $status,
        string $cooperation,
        bool $archived = false,
        ?string $rejectedReason = null,
        ?Carbon $reviewedAt = null,
    ): Program {
        return Program::create([
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'pillar_id' => $this->pillar->id,
            'jenis_kerjasama' => $cooperation,
            'nama_program' => $name,
            'deskripsi_program' => 'Deskripsi '.$name,
            'sasaran_program' => 'Sasaran '.$name,
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra '.$name,
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'tujuan_program' => 'Tujuan '.$name,
            'status' => $status,
            'is_archived' => $archived,
            'created_by' => $creator->id,
            'fase1_rejected_reason' => $rejectedReason,
            'fase1_reviewed_by' => $rejectedReason ? $this->superAdmin->id : null,
            'fase1_reviewed_at' => $rejectedReason ? ($reviewedAt ?? now()) : null,
        ]);
    }

    /**
     * @param  list<string>  $types
     */
    private function addDocuments(Program $program, array $types): void
    {
        foreach ($types as $type) {
            $program->documents()->create([
                'document_type' => $type,
                'nama_dokumen' => "{$type}.pdf",
                'file_path' => "programs/{$program->id}/{$type}.pdf",
                'uploaded_at' => now(),
            ]);
        }
    }

    private function user(string $role, string $name): User
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, null);

        return User::factory()->create([
            'name' => $name,
            'nama_depan' => $firstName,
            'nama_belakang' => $lastName,
            'username' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'role' => $role,
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
