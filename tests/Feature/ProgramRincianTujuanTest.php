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

class ProgramRincianTujuanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Pillar $social;

    private Pillar $economic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->social = Pillar::create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
        ]);
        $this->economic = Pillar::create([
            'name' => 'Ekonomi',
            'slug' => 'ekonomi',
            'color_hex' => '#f59e0b',
        ]);
    }

    public function test_rincian_filters_pillar_paginates_and_hides_unapproved_programs(): void
    {
        foreach (range(1, 9) as $index) {
            $this->program("Program Sosial {$index}", $this->social, 'approved_fase1');
        }

        $this->program('Program Ekonomi', $this->economic, 'completed');
        $this->program('Program Menunggu', $this->social, 'pending_fase1');
        $this->program('Program Ditolak', $this->social, 'rejected_fase1');

        $firstPage = $this->actingAs($this->user)
            ->get(route('program.rincian', ['pilar' => 'sosial']));

        $firstPage
            ->assertOk()
            ->assertDontSee('Program Ekonomi')
            ->assertDontSee('Program Menunggu')
            ->assertDontSee('Program Ditolak')
            ->assertSee('pilar=sosial&amp;page=2', false);
        $this->assertCount(8, $firstPage->viewData('programs')->items());

        $secondPage = $this->actingAs($this->user)
            ->get(route('program.rincian', ['pilar' => 'sosial', 'page' => 2]));

        $secondPage->assertOk()->assertDontSee('Program Ekonomi');
        $this->assertCount(1, $secondPage->viewData('programs')->items());
        $this->assertStringContainsString('pilar=sosial', $secondPage->viewData('programs')->previousPageUrl());
    }

    public function test_rincian_cards_have_pillar_aware_hover_and_keyboard_hooks(): void
    {
        $this->program('Program Dengan Animasi Pilar', $this->social, 'approved_fase1');

        $this->actingAs($this->user)
            ->get(route('program.rincian', ['pilar' => 'sosial']))
            ->assertOk()
            ->assertSee('program-pillar-filter', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('program-rincian-card', false)
            ->assertSee('program-rincian-card-image', false)
            ->assertSee('data-pillar-card="sosial"', false);
    }

    public function test_rincian_cards_label_pks_non_pks_and_bantuan_tjsl(): void
    {
        $pks = $this->program('Program Kerja Sama PKS', $this->social, 'completed');
        $pks->update(['jenis_kerjasama' => 'pks']);
        $this->program('Program Kerja Sama Non-PKS', $this->social, 'completed');
        $this->csr('Program Bantuan TJSL', $this->social, 'completed');

        $this->actingAs($this->user)
            ->get(route('program.rincian', ['pilar' => 'sosial']))
            ->assertOk()
            ->assertSee('data-program-kind="pks"', false)
            ->assertSee('data-program-kind="non-pks"', false)
            ->assertSee('data-program-kind="bantuan-tjsl"', false)
            ->assertSee('Bantuan TJSL');
    }

    public function test_rincian_combines_approved_internal_and_csr_programs_with_server_side_filter_and_pagination(): void
    {
        foreach (range(1, 7) as $index) {
            $this->program("Program Internal {$index}", $this->social, 'approved_fase1');
        }

        $this->csr('CSR Sosial Tayang', $this->social, 'approved_fase1');
        $this->csr('CSR Sosial Halaman Dua', $this->social, 'completed');
        $this->csr('CSR Ekonomi', $this->economic, 'completed');
        $this->csr('CSR Menunggu', $this->social, 'pending_fase1');

        $firstPage = $this->actingAs($this->user)
            ->get(route('program.rincian', ['pilar' => 'sosial']));

        $firstPage
            ->assertOk()
            ->assertDontSee('CSR Ekonomi')
            ->assertDontSee('CSR Menunggu')
            ->assertSee('pilar=sosial&amp;page=2', false);
        $this->assertCount(8, $firstPage->viewData('programs')->items());

        $secondPage = $this->actingAs($this->user)
            ->get(route('program.rincian', ['pilar' => 'sosial', 'page' => 2]));

        $secondPage
            ->assertOk()
            ->assertDontSee('CSR Ekonomi')
            ->assertDontSee('CSR Menunggu');
        $this->assertCount(1, $secondPage->viewData('programs')->items());
        $this->assertStringContainsString('pilar=sosial', $secondPage->viewData('programs')->previousPageUrl());

        $titles = collect($firstPage->viewData('programs')->items())
            ->concat($secondPage->viewData('programs')->items())
            ->pluck('title');

        $this->assertTrue($titles->contains('CSR Sosial Tayang'));
        $this->assertTrue($titles->contains('CSR Sosial Halaman Dua'));
    }

    public function test_public_csr_detail_renders_real_data_and_protects_document_routes(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $csr = $this->csr('Bantuan CSR Dinamis', $this->social, 'completed', [
            'deskripsi_bantuan' => 'Deskripsi bantuan yang berasal dari database.',
            'rencana_anggaran' => 50000000,
            'realisasi_anggaran' => 25000000,
        ]);
        $csr->targets()->createMany([
            ['target_text' => 'Target penerima pertama', 'order' => 0],
            ['target_text' => 'Target penerima kedua', 'order' => 1],
        ]);
        $detail = $csr->details()->create([
            'rincian_kegiatan' => 'Penyaluran bantuan',
            'penerima_bantuan' => 'Masyarakat Desa A',
            'jenis_bantuan' => 'Uang Tunai',
            'quality' => '50 Kepala Keluarga',
            'nominal_bantuan' => 25000000,
            'order' => 0,
        ]);
        $detail->photos()->create([
            'file_path' => UploadedFile::fake()->image('detail.jpg')->store('csr/detail', 'public'),
            'caption' => 'Foto penyaluran',
        ]);
        $csr->photos()->create([
            'file_path' => UploadedFile::fake()->image('bast.jpg')->store('csr/photos', 'public'),
            'caption' => 'Foto utama bantuan',
            'order' => 0,
        ]);
        Storage::disk('local')->put('csr/documents/bast.pdf', 'dokumen-bast');
        $document = $csr->documents()->create([
            'document_type' => 'bast',
            'nama_dokumen' => 'BAST Bantuan',
            'file_path' => 'csr/documents/bast.pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('program.csr.detail', $csr))
            ->assertOk()
            ->assertSee('Bantuan CSR Dinamis')
            ->assertSee('Target penerima pertama')
            ->assertSee('Penyaluran bantuan')
            ->assertSee('50 Kepala Keluarga')
            ->assertSee('realisasi-gallery-track', false)
            ->assertSee('Berita Acara Serah Terima (BAST)')
            ->assertSee(route('program.csr.documents.view', [$csr, $document]), false)
            ->assertSee(route('program.csr.documents.download', [$csr, $document]), false);

        $this->actingAs($this->user)
            ->get(route('program.csr.documents.view', [$csr, $document]))
            ->assertOk();

        $other = $this->csr('CSR Lain', $this->social, 'completed');
        $this->actingAs($this->user)
            ->get(route('program.csr.documents.view', [$other, $document]))
            ->assertNotFound();

        $pending = $this->csr('CSR Belum Disetujui', $this->social, 'pending_fase1');
        $this->actingAs($this->user)
            ->get(route('program.csr.detail', $pending))
            ->assertNotFound();
    }

    public function test_public_internal_detail_exposes_view_and_download_for_its_own_documents_only(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $program = $this->program('Program Dokumen Internal', $this->social, 'completed');
        $program->photos()->create([
            'file_path' => UploadedFile::fake()->image('realisasi.jpg')->store('program/photos', 'public'),
            'caption' => 'Dokumentasi realisasi',
            'is_cover' => true,
            'order' => 0,
        ]);
        Storage::disk('local')->put('program/documents/bast.pdf', 'dokumen-program');
        $document = $program->documents()->create([
            'document_type' => 'bast',
            'nama_dokumen' => 'BAST Program Internal',
            'file_path' => 'program/documents/bast.pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('program.detail', $program->slug))
            ->assertOk()
            ->assertSee('realisasi-gallery-track', false)
            ->assertSee(route('program.documents.view', [$program, $document]), false)
            ->assertSee(route('program.documents.download', [$program, $document]), false);

        $this->actingAs($this->user)
            ->get(route('program.documents.view', [$program, $document]))
            ->assertOk();

        $this->actingAs($this->user)
            ->get(route('program.documents.download', [$program, $document]))
            ->assertDownload('BAST Program Internal.pdf');

        $other = $this->program('Program Internal Lain', $this->social, 'completed');
        $this->actingAs($this->user)
            ->get(route('program.documents.view', [$other, $document]))
            ->assertNotFound();

        $program->update(['status' => 'pending_fase1']);
        $this->actingAs($this->user)
            ->get(route('program.documents.view', [$program, $document]))
            ->assertNotFound();
    }

    public function test_admin_store_saves_single_program_goal_and_public_detail_uses_it(): void
    {
        $response = $this->actingAs($this->user, 'admin')->post(route('admin.programs.store'), [
            'pillar_id' => $this->social->id,
            'jenis_kerjasama' => 'non_pks',
            'nama_program' => 'Program Tujuan Dinamis',
            'deskripsi_program' => 'Deskripsi unik program.',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 100000,
            'tujuan_program' => 'Tujuan program tunggal seperti form semula.',
            'action' => 'draft',
        ]);

        $program = Program::where('nama_program', 'Program Tujuan Dinamis')->firstOrFail();
        $response->assertRedirect(route('admin.programs.show', $program));
        $this->assertSame('Tujuan program tunggal seperti form semula.', $program->tujuan_program);
        $this->assertSame(0, $program->tujuan()->count());

        $program->update(['status' => 'approved_fase1']);

        $this->actingAs($this->user, 'web')
            ->get(route('program.detail', $program->slug))
            ->assertOk()
            ->assertSee('Tujuan program tunggal seperti form semula.')
            ->assertDontSee('Program ini dijalankan secara kolaboratif, terukur, dan berkelanjutan');
    }

    public function test_existing_repeatable_goal_data_remains_readable_during_form_transition(): void
    {
        $program = $this->program('Program Tujuan Lama', $this->social, 'draft');
        $program->tujuan()->createMany([
            ['deskripsi' => 'Tujuan lama pertama', 'urutan' => 0],
            ['deskripsi' => 'Tujuan lama kedua', 'urutan' => 1],
        ]);

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('name="tujuan_program"', false)
            ->assertSee('Tujuan lama pertama')
            ->assertSee('Tujuan lama kedua')
            ->assertDontSee('Tambah Tujuan Program');

        $program->update(['status' => 'approved_fase1']);

        $this->actingAs($this->user, 'web')
            ->get(route('program.detail', $program->slug))
            ->assertOk()
            ->assertSee('Tujuan lama pertama')
            ->assertSee('Tujuan lama kedua');
    }

    private function program(string $name, Pillar $pillar, string $status): Program
    {
        return Program::create([
            'slug' => str($name)->slug(),
            'pillar_id' => $pillar->id,
            'jenis_kerjasama' => 'non_pks',
            'nama_program' => $name,
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 100,
            'realisasi_anggaran' => 50,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function csr(string $name, Pillar $pillar, string $status, array $overrides = []): BantuanCsr
    {
        return BantuanCsr::create([
            'nama_program_bantuan' => $name,
            'deskripsi_bantuan' => 'Deskripsi CSR',
            'pillar_id' => $pillar->id,
            'rencana_anggaran' => 100,
            'realisasi_anggaran' => 50,
            'status' => $status,
            'created_by' => $this->user->id,
            ...$overrides,
        ]);
    }
}
