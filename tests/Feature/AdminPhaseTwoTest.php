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

class AdminPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'must_change_password' => false,
            'username' => 'admin-test',
        ]);
    }

    private function phaseOneDocuments(): array
    {
        return collect(Program::PHASE_ONE_DOCUMENTS)
            ->mapWithKeys(fn ($label, $type) => [
                $type => [
                    'nama' => $label,
                    'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
                ],
            ])
            ->all();
    }

    public function test_admin_can_create_and_submit_program_phase_one(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.programs.store'), [
            'pillar_id' => $pillar->id,
            'nama_program' => 'Program Uji',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 0,
            'tujuan_program' => 'Tujuan',
            'documents' => $this->phaseOneDocuments(),
            'action' => 'submit',
        ]);

        $program = Program::firstOrFail();

        $response->assertRedirect(route('admin.programs.show', $program));
        $this->assertSame('pending_fase1', $program->status);
        $this->assertCount(5, $program->documents);
        $this->assertDatabaseHas('status_logs', [
            'related_type' => 'program',
            'to_status' => 'pending_fase1',
        ]);
    }

    public function test_admin_can_create_repeatable_bantuan_csr_draft(): void
    {
        $admin = $this->admin();
        $pillar = Pillar::create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
        ]);
        $response = $this->actingAs($admin, 'admin')->post(route('admin.assistance.store'), [
            'nama_program_bantuan' => 'Bantuan Uji',
            'deskripsi_bantuan' => 'Deskripsi',
            'pillar_id' => $pillar->id,
            'rencana_anggaran' => 500000,
            'realisasi_anggaran' => 0,
            'targets' => ['Target satu', 'Target dua'],
            'details' => [[
                'rincian_kegiatan' => 'Kegiatan',
                'penerima_bantuan' => 'Warga',
                'jenis_bantuan' => 'Barang',
                'quality' => '10 unit',
                'nominal_bantuan' => 500000,
            ]],
            'action' => 'draft',
        ]);

        $item = BantuanCsr::firstOrFail();
        $response->assertRedirect(route('admin.assistance.show', $item));
        $this->assertSame('draft', $item->status);
        $this->assertCount(2, $item->targets);
        $this->assertCount(1, $item->details);
    }

    public function test_admin_cannot_view_another_admin_program(): void
    {
        $owner = $this->admin();
        $other = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
            'username' => 'other',
        ]);
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $program = Program::create([
            'slug' => 'private',
            'pillar_id' => $pillar->id,
            'nama_program' => 'Private',
            'deskripsi_program' => 'x',
            'sasaran_program' => 'x',
            'lokasi_program' => 'x',
            'mitra_program' => 'x',
            'rencana_anggaran' => 0,
            'realisasi_anggaran' => 0,
            'tujuan_program' => 'x',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertForbidden();
    }

    public function test_all_primary_admin_pages_render(): void
    {
        $admin = $this->admin();
        Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);

        foreach ([
            'admin.home',
            'admin.programs.index',
            'admin.programs.create',
            'admin.assistance.index',
            'admin.assistance.create',
            'admin.notifications',
            'admin.profile',
        ] as $route) {
            $this->actingAs($admin, 'admin')->get(route($route))->assertOk();
        }
    }

    public function test_admin_home_uses_fixed_shell_and_constrained_horizontal_logo(): void
    {
        $admin = $this->admin();
        foreach ([
            ['Sosial', 'sosial', '#2563eb'],
            ['Ekonomi', 'ekonomi', '#f59e0b'],
            ['Lingkungan', 'lingkungan', '#16a34a'],
            ['Hukum & Tata Kelola', 'hukum-tata-kelola', '#dc2626'],
        ] as [$name, $slug, $color]) {
            Pillar::create(compact('name', 'slug') + ['color_hex' => $color]);
        }

        $this->actingAs($admin, 'admin')->get(route('admin.home'))
            ->assertOk()
            ->assertSee('admin-header')
            ->assertSee('admin-sidebar')
            ->assertSee('admin-main')
            ->assertSee('lensa-tjsl-inka-navbar.png')
            ->assertSee('Selamat datang di dashboard Admin')
            ->assertSee('Hukum &amp; Tata Kelola', false)
            ->assertSee('Program TJSL')
            ->assertSee('Status Bantuan TJSL')
            ->assertSee('Buat Bantuan TJSL');
    }

    public function test_create_forms_use_verified_design_tokens_and_reference_structure(): void
    {
        $admin = $this->admin();
        Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);

        $this->actingAs($admin, 'admin')->get(route('admin.programs.create'))
            ->assertOk()
            ->assertSee('Pilih Jenis Kerja Sama Program TJSL')
            ->assertSee('PKS')
            ->assertSee('NON-PKS')
            ->assertSee(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'pks']), false)
            ->assertSee(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'non-pks']), false)
            ->assertDontSee('Program Internal')
            ->assertDontSee('Program Eksternal');

        $this->actingAs($admin, 'admin')->get(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'pks']))
            ->assertOk()
            ->assertSee('#eeeeee')
            ->assertSee('#ff0009')
            ->assertSee('#2653ff')
            ->assertSee('#fd6eff')
            ->assertSee('#0a4e6f')
            ->assertSee('Program TJSL INKA')
            ->assertSee('Proposal Pengajuan Program')
            ->assertSee('Perjanjian Kerja Sama')
            ->assertDontSee('Berita Acara Serah Terima (BAST)')
            ->assertDontSee('A. Proposal Pengajuan Program')
            ->assertDontSee('E. Perjanjian Kerja Sama')
            ->assertDontSee('F. Berita Acara Serah Terima (BAST)')
            ->assertDontSee('Dokumen BAST baru bisa diunggah setelah dokumen awal disetujui')
            ->assertDontSee('Fase 1')
            ->assertDontSee('name="dokumen_f"', false)
            ->assertSee('name="jenis_kerjasama"', false)
            ->assertSee('value="pks"', false)
            ->assertSee('Tambah Dokumen')
            ->assertDontSee('name="photos[]"', false)
            ->assertDontSee('Tambah Foto Dokumentasi');

        $this->actingAs($admin, 'admin')->get(route('admin.programs.cooperation.form', ['jenisKerjasama' => 'non-pks']))
            ->assertOk()
            ->assertSee('value="non_pks"', false)
            ->assertDontSee('name="photos[]"', false)
            ->assertDontSee('Tambah Foto Dokumentasi');

        $this->actingAs($admin, 'admin')->get(route('admin.assistance.create'))
            ->assertOk()
            ->assertSee('Bantuan TJSL INKA')
            ->assertSee('Tambah Tujuan Capaian')
            ->assertSee('Tambah Rincian Lainnya')
            ->assertSee('Proposal Pengajuan Program')
            ->assertSee('Kelengkapan Survei')
            ->assertDontSee('name="details[0][photos][]"', false)
            ->assertDontSee('Tambah Foto Dokumentasi')
            ->assertDontSee('name="program_id"', false)
            ->assertDontSee('Formulir Kajian Proposal');
    }

    public function test_admin_dashboard_has_one_clickable_block_per_pillar_and_filtered_detail_list(): void
    {
        $admin = $this->admin();
        $pillar = Pillar::create([
            'name' => 'Hukum & Tata Kelola',
            'slug' => 'hukum-tata-kelola',
            'color_hex' => '#dc2626',
        ]);

        foreach (range(1, 6) as $number) {
            Program::create([
                'slug' => 'hukum-'.$number,
                'pillar_id' => $pillar->id,
                'nama_program' => 'Program Hukum '.$number,
                'deskripsi_program' => 'x',
                'sasaran_program' => 'x',
                'lokasi_program' => 'x',
                'mitra_program' => 'x',
                'rencana_anggaran' => 1,
                'tujuan_program' => 'x',
                'created_by' => $admin->id,
            ]);
        }

        $dashboard = $this->actingAs($admin, 'admin')->get(route('admin.home'));
        $dashboard->assertOk()
            ->assertSee('data-pillar="hukum-tata-kelola"', false)
            ->assertSee('Program TJSL Hukum &amp; Tata Kelola', false)
            ->assertSee('6 Program');
        $this->assertSame(1, substr_count($dashboard->getContent(), 'data-pillar="hukum-tata-kelola"'));

        $list = $this->get(route('admin.programs.index', ['pillar' => 'hukum-tata-kelola']));
        $list->assertOk()->assertSee('Kategori: Hukum &amp; Tata Kelola', false);
        foreach (range(1, 6) as $number) {
            $list->assertSee('Program Hukum '.$number);
        }
    }
}
