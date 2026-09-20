<?php

namespace Tests\Feature;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BantuanCsrOverviewIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    /** @var array<string, Pillar> */
    private array $pillars = [];

    protected function setUp(): void
    {
        parent::setUp();

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
            $this->pillars[$slug] = Pillar::create([
                'name' => $name,
                'slug' => $slug,
                'color_hex' => $colorHex,
            ]);
        }
    }

    public function test_bantuan_csr_form_uses_shared_pillar_choices_and_literal_document_codes(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.create'));

        $response->assertOk()
            ->assertSee('name="pillar_id"', false)
            ->assertSee('Sosial')
            ->assertSee('Ekonomi')
            ->assertSee('Lingkungan')
            ->assertSee('Hukum &amp; Tata Kelola', false)
            ->assertSee('name="documents[A][file]"', false)
            ->assertSee('name="documents[B][file]"', false)
            ->assertDontSee('name="documents[proposal_pengajuan_program][file]"', false)
            ->assertDontSee('name="documents[kelengkapan_survei][file]"', false);
    }

    public function test_public_overview_integrates_approved_csr_without_regressing_program_tjsl(): void
    {
        $approved = $this->csr(
            'CSR Pendidikan Disetujui',
            'approved_fase1',
            $this->pillars['sosial'],
            ['A', 'B'],
        );
        $pendingBast = $this->csr(
            'CSR Menunggu BAST',
            'pending_fase2',
            $this->pillars['ekonomi'],
            ['A', 'bast'],
        );
        $completed = $this->csr(
            'CSR Selesai',
            'completed',
            $this->pillars['lingkungan'],
            ['A', 'B', 'bast'],
        );

        foreach ([
            ['CSR Draft Privat', 'draft'],
            ['CSR Belum Disetujui', 'pending_fase1'],
            ['CSR Ditolak', 'rejected_fase1'],
        ] as [$name, $status]) {
            $this->csr($name, $status, $this->pillars['hukum-tata-kelola']);
        }

        $internal = Program::create([
            'slug' => 'internal-regresi-'.Str::lower(Str::random(6)),
            'pillar_id' => $this->pillars['sosial']->id,
            'jenis_kerjasama' => 'pks',
            'nama_program' => 'Internal Tetap Tampil',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'tujuan_program' => 'Tujuan',
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview'));

        $response->assertOk()
            ->assertSee('CSR Pendidikan Disetujui')
            ->assertSee('CSR Menunggu BAST')
            ->assertSee('CSR Selesai')
            ->assertDontSee('CSR Draft Privat')
            ->assertDontSee('CSR Belum Disetujui')
            ->assertDontSee('CSR Ditolak')
            ->assertSee('Internal Tetap Tampil')
            ->assertSee('#7c3aed', false);

        $this->assertCsrRow(
            $response,
            $approved,
            'sosial',
            [
                'A' => 'complete',
                'B' => 'complete',
                'C' => 'na',
                'D' => 'na',
                'E' => 'na',
                'F' => 'in-progress',
            ],
        );
        $this->assertCsrRow(
            $response,
            $pendingBast,
            'ekonomi',
            [
                'A' => 'complete',
                'B' => 'incomplete',
                'C' => 'na',
                'D' => 'na',
                'E' => 'na',
                'F' => 'complete',
            ],
        );
        $this->assertCsrRow(
            $response,
            $completed,
            'lingkungan',
            [
                'A' => 'complete',
                'B' => 'complete',
                'C' => 'na',
                'D' => 'na',
                'E' => 'na',
                'F' => 'complete',
            ],
        );

        $this->assertNotNull($this->programRow($response, $internal->id, 'internal'));
    }

    public function test_public_overview_searches_csr_by_program_name_and_keeps_approval_filter(): void
    {
        $matching = $this->csr(
            'Bantuan CSR Sekolah Madiun',
            'approved_fase1',
            $this->pillars['sosial'],
            ['A', 'B'],
        );
        $other = $this->csr(
            'Bantuan CSR Kesehatan',
            'completed',
            $this->pillars['sosial'],
            ['A', 'B'],
        );
        $hidden = $this->csr(
            'Bantuan Sekolah Masih Menunggu',
            'pending_fase1',
            $this->pillars['sosial'],
            ['A', 'B'],
        );

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview', ['keyword' => 'Sekolah']));

        $response->assertOk()
            ->assertSee($matching->nama_program_bantuan)
            ->assertDontSee($other->nama_program_bantuan)
            ->assertDontSee($hidden->nama_program_bantuan)
            ->assertSee('value="Sekolah"', false);

        $this->assertNotNull($this->programRow($response, $matching->id, 'csr'));
        $this->assertNull($this->programRow($response, $hidden->id, 'csr'));
    }

    public function test_public_overview_monitoring_refreshes_only_when_visible_data_changes(): void
    {
        $item = $this->csr(
            'CSR Realtime Pendidikan',
            'approved_fase1',
            $this->pillars['sosial'],
            ['A'],
        );

        $page = $this->actingAs($this->admin, 'web')
            ->get(route('program.overview', ['keyword' => 'Realtime']));

        $page->assertOk()
            ->assertSee(route('program.overview.monitoring'), false)
            ->assertSee('const pollInterval = 10000;', false)
            ->assertSee('document.visibilityState', false)
            ->assertSee('searchHasPendingInput', false);
        $this->assertMatchesRegularExpression(
            '/data-document-column="F"\s+data-document-state="in-progress"/',
            $page->getContent(),
        );

        preg_match(
            '/data-monitoring-signature="([a-f0-9]{64})"/',
            $page->getContent(),
            $pageSignature,
        );
        $this->assertArrayHasKey(1, $pageSignature);

        $this->actingAs($this->admin, 'web')
            ->getJson(route('program.overview.monitoring', [
                'keyword' => 'Realtime',
                'signature' => $pageSignature[1],
            ]))
            ->assertNoContent();

        $firstRefresh = $this->actingAs($this->admin, 'web')
            ->getJson(route('program.overview.monitoring', ['keyword' => 'Realtime']));

        $firstRefresh->assertOk()
            ->assertJsonStructure(['signature', 'html']);

        $signature = $firstRefresh->json('signature');
        $this->assertIsString($signature);
        $this->assertSame(64, strlen($signature));
        $this->assertStringContainsString('CSR Realtime Pendidikan', $firstRefresh->json('html'));
        $this->assertStringContainsString('action="'.route('program.overview').'"', $firstRefresh->json('html'));

        $unchangedRefresh = $this->actingAs($this->admin, 'web')
            ->getJson(route('program.overview.monitoring', [
                'keyword' => 'Realtime',
                'signature' => $signature,
            ]));

        $unchangedRefresh->assertNoContent();

        $item->documents()->create([
            'document_type' => 'B',
            'nama_dokumen' => 'Kelengkapan Survei.pdf',
            'file_path' => "bantuan-csr/{$item->id}/B.pdf",
            'uploaded_at' => now(),
        ]);

        $changedRefresh = $this->actingAs($this->admin, 'web')
            ->getJson(route('program.overview.monitoring', [
                'keyword' => 'Realtime',
                'signature' => $signature,
            ]));

        $changedRefresh->assertOk()
            ->assertJsonStructure(['signature', 'html']);
        $this->assertNotSame($signature, $changedRefresh->json('signature'));
        $this->assertStringContainsString(
            'data-document-column="B"',
            $changedRefresh->json('html'),
        );
        $this->assertStringContainsString(
            'data-document-state="complete"',
            $changedRefresh->json('html'),
        );
        $this->assertMatchesRegularExpression(
            '/data-document-column="F"\s+data-document-state="in-progress"/',
            $changedRefresh->json('html'),
        );

        $phaseOneSignature = $changedRefresh->json('signature');
        $item->documents()->create([
            'document_type' => 'bast',
            'nama_dokumen' => 'BAST CSR.pdf',
            'file_path' => "bantuan-csr/{$item->id}/bast.pdf",
            'uploaded_at' => now(),
        ]);
        $item->update(['status' => 'pending_fase2']);

        $bastRefresh = $this->actingAs($this->admin, 'web')
            ->getJson(route('program.overview.monitoring', [
                'keyword' => 'Realtime',
                'signature' => $phaseOneSignature,
            ]));

        $bastRefresh->assertOk()
            ->assertJsonStructure(['signature', 'html']);
        $this->assertNotSame($phaseOneSignature, $bastRefresh->json('signature'));
        $this->assertMatchesRegularExpression(
            '/data-document-column="F"\s+data-document-state="complete"/',
            $bastRefresh->json('html'),
        );
    }

    /**
     * @param  list<string>  $documents
     */
    private function csr(
        string $name,
        string $status,
        Pillar $pillar,
        array $documents = [],
    ): BantuanCsr {
        $reviewed = in_array($status, BantuanCsr::OVERVIEW_STATUSES, true);
        $item = BantuanCsr::create([
            'nama_program_bantuan' => $name,
            'deskripsi_bantuan' => 'Deskripsi '.$name,
            'pillar_id' => $pillar->id,
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'status' => $status,
            'created_by' => $this->admin->id,
            'fase1_reviewed_by' => $reviewed ? $this->superAdmin->id : null,
            'fase1_reviewed_at' => $reviewed ? now() : null,
        ]);

        foreach ($documents as $type) {
            $item->documents()->create([
                'document_type' => $type,
                'nama_dokumen' => "{$type}.pdf",
                'file_path' => "bantuan-csr/{$item->id}/{$type}.pdf",
                'uploaded_at' => now(),
            ]);
        }

        return $item;
    }

    /**
     * @param  array<string, string>  $expectedStates
     */
    private function assertCsrRow(
        TestResponse $response,
        BantuanCsr $item,
        string $pillar,
        array $expectedStates = [],
    ): void {
        $row = $this->programRow($response, $item->id, 'csr');

        $this->assertInstanceOf(DOMElement::class, $row);
        $this->assertFalse($row->hasAttribute('data-cooperation-kind'));
        $this->assertStringContainsString('CSR', $row->textContent);
        $this->assertSame($pillar, $row->parentNode?->attributes?->getNamedItem('data-pillar-group')?->nodeValue);

        $xpath = new DOMXPath($row->ownerDocument);
        foreach ($expectedStates as $column => $state) {
            $cells = $xpath->query(
                './/*[@data-document-column="'.$column.'" and @data-document-state="'.$state.'"]',
                $row,
            );
            $this->assertSame(
                1,
                $cells?->length,
                "Kolom {$column} CSR #{$item->id} seharusnya {$state}.",
            );
        }
    }

    private function programRow(
        TestResponse $response,
        int $id,
        string $kind,
    ): ?DOMElement {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $row = $xpath
            ->query(
                '//*[@data-program-id="'.$id.'" and @data-program-kind="'.$kind.'"]',
            )
            ?->item(0);

        return $row instanceof DOMElement ? $row : null;
    }
}
