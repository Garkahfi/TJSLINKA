<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\TpbDashboard;
use App\Models\User;
use App\Models\WilayahOperasional;
use Database\Seeders\BidangPrioritasSeeder;
use Database\Seeders\WilayahOperasionalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicHomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_reads_sheet_cache_tables_instead_of_aggregating_program_submissions(): void
    {
        $user = User::factory()->create();
        $sosial = Pillar::create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
            'dashboard_rencana_anggaran' => 3000,
            'dashboard_realisasi_anggaran' => 1300,
        ]);
        $ekonomi = Pillar::create([
            'name' => 'Ekonomi',
            'slug' => 'ekonomi',
            'color_hex' => '#f59e0b',
            'dashboard_rencana_anggaran' => 5000,
            'dashboard_realisasi_anggaran' => 2500,
        ]);
        $lingkungan = Pillar::create(['name' => 'Lingkungan', 'slug' => 'lingkungan', 'color_hex' => '#16a34a']);
        $hukum = Pillar::create(['name' => 'Hukum & Tata Kelola', 'slug' => 'hukum-tata-kelola', 'color_hex' => '#dc2626']);
        $madiun = WilayahOperasional::create([
            'nama' => 'Kota Madiun',
            'latitude' => -7.6298,
            'longitude' => 111.5239,
            'realisasi_anggaran' => 1300,
        ]);
        WilayahOperasional::create([
            'nama' => 'Kab. Banyuwangi',
            'latitude' => -8.2192,
            'longitude' => 114.3691,
            'realisasi_anggaran' => 2500,
        ]);
        $this->seed(BidangPrioritasSeeder::class);
        TpbDashboard::create([
            'nomor_tpb' => 1,
            'nama_tpb' => 'Tanpa Kemiskinan',
            'rencana_anggaran' => 2000,
            'realisasi_anggaran' => 1200,
        ]);

        $this->createProgram($user, $sosial, 'completed', 'program-tidak-menjadi-sumber-dashboard', 99999, 99999);
        $this->createProgram($user, $sosial, 'pending_fase1', 'program-pending', 99999, 99999);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertOk()
            ->assertSee('class="tjsl-report-frame"', false)
            ->assertSee('class="report-grid"', false)
            ->assertSee('id="per-pilar-chart"', false)
            ->assertSee('id="tpb-chart"', false)
            ->assertSee('data-per-tpb=', false)
            ->assertSee('id="priority-chart"', false)
            ->assertSee('id="region-chart"', false)
            ->assertSee('id="peta-wilayah"', false)
            ->assertSee('Realisasi Anggaran Program TJSL Tahun')
            ->assertSee('Download Laporan')
            ->assertSee('Realisasi Anggaran untuk Bidang Prioritas')
            ->assertDontSee('Realisasi Anggaran untuk Program Unggulan')
            ->assertSee('class="pumk-report-frame pumk-bri-frame"', false)
            ->assertSee('Dashboard Program PUMK BRI')
            ->assertSee('id="pumk-portfolio-chart"', false)
            ->assertSee('id="pumk-quality-chart"', false)
            ->assertSee('id="pumk-bri-map"', false)
            ->assertSee('id="pumk-outstanding-month-chart"', false)
            ->assertSee('id="pumk-bri-data-mitra"', false)
            ->assertSee('Input RKA Penyaluran')
            ->assertSee('class="faq-frame"', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('Lensa TJSL INKA adalah platform digital')
            ->assertSee('data-account-button', false)
            ->assertSee('data-account-menu', false)
            ->assertSee('Profil')
            ->assertSee('Logout')
            ->assertSee('href="'.route('public.profile').'"', false)
            ->assertSee('action="'.route('public.logout').'"', false)
            ->assertSee('Kota Madiun')
            ->assertSee('Kab. Banyuwangi')
            ->assertSee('Bidang Pendidikan')
            ->assertSee('Bidang Pengembangan UMK')
            ->assertSee('Bidang Lingkungan')
            ->assertSee('Lingkungan')
            ->assertSee('Hukum &amp; Tata Kelola', false);

        $perPilar = $response->viewData('perPilar');
        $perWilayah = $response->viewData('perWilayah');
        $bidangPrioritas = $response->viewData('bidangPrioritas');
        $tpbDashboard = $response->viewData('tpbDashboard');
        $sosialTotal = $perPilar->firstWhere('pillar_id', $sosial->id);
        $lingkunganTotal = $perPilar->firstWhere('pillar_id', $lingkungan->id);
        $hukumTotal = $perPilar->firstWhere('pillar_id', $hukum->id);
        $madiunTotal = $perWilayah->firstWhere('id', $madiun->id);

        $this->assertCount(4, $perPilar);
        $this->assertCount(3, $bidangPrioritas);
        $this->assertCount(1, $tpbDashboard);
        $this->assertSame(8000.0, $response->viewData('totalRencana'));
        $this->assertSame(3800.0, $response->viewData('totalRealisasi'));
        $this->assertSame(47.5, $response->viewData('penyerapan'));
        $this->assertSame(3000.0, (float) $sosialTotal->total_rencana);
        $this->assertSame(1300.0, (float) $sosialTotal->total_realisasi);
        $this->assertSame(0.0, (float) $lingkunganTotal->total_rencana);
        $this->assertSame(0.0, (float) $lingkunganTotal->total_realisasi);
        $this->assertSame(0.0, (float) $hukumTotal->total_rencana);
        $this->assertSame(0.0, (float) $hukumTotal->total_realisasi);
        $this->assertSame(1300.0, (float) $madiunTotal->realisasi_anggaran);
    }

    public function test_region_is_not_part_of_admin_program_form_or_storage(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $formResponse = $this->actingAs($admin, 'admin')->get(
            route('admin.programs.cooperation.form', ['jenisKerjasama' => 'pks']),
        );

        $formResponse->assertOk()
            ->assertSee('Lokasi Program')
            ->assertDontSee('name="wilayah_id"', false)
            ->assertDontSee('Pilih wilayah');

        $response = $this->actingAs($admin, 'admin')->post(route('admin.programs.store'), [
            'pillar_id' => $pillar->id,
            'nama_program' => 'Program Wilayah',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Kelurahan Kartoharjo, Kota Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'tujuan_program' => 'Tujuan',
            'action' => 'draft',
        ]);

        $program = Program::query()->firstOrFail();

        $response->assertRedirect(route('admin.programs.show', $program));
        $this->assertFalse(Schema::hasColumn('programs', 'wilayah_id'));
        $this->assertSame('Kelurahan Kartoharjo, Kota Madiun', $program->lokasi_program);
    }

    public function test_public_home_uses_all_regions_and_keeps_wilayah_lainnya_at_the_bottom(): void
    {
        $user = User::factory()->create();
        $this->seed(WilayahOperasionalSeeder::class);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertOk()
            ->assertSee('Kota Madiun')
            ->assertSee('Kab. Madiun')
            ->assertSee('Kab. Banyuwangi')
            ->assertSee('Wilayah Lainnya')
            ->assertSee('Kab. Ngawi')
            ->assertSee('Kab. Magetan')
            ->assertSee('Kab. Ponorogo')
            ->assertSee('Kab. Pacitan')
            ->assertSee('id="region-chart"', false)
            ->assertSee('id="peta-wilayah"', false);

        $perWilayah = $response->viewData('perWilayah');

        $this->assertCount(8, $perWilayah);
        $this->assertSame('Kota Madiun', $perWilayah->first()->nama);
        $this->assertSame('Wilayah Lainnya', $perWilayah->last()->nama);
        $this->assertSame(0.0, (float) $perWilayah->firstWhere('nama', 'Kab. Pacitan')->realisasi_anggaran);
        $response->assertSee('Chart.defaults.devicePixelRatio = chartPixelRatio', false);
    }

    public function test_public_logout_does_not_clear_the_separate_internal_admin_session(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin');
        $response = $this->actingAs($admin, 'web')->post(route('public.logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    private function createProgram(
        User $user,
        Pillar $pillar,
        string $status,
        string $slug,
        float $rencana,
        float $realisasi,
    ): Program {
        return Program::create([
            'slug' => $slug,
            'pillar_id' => $pillar->id,
            'nama_program' => ucfirst(str_replace('-', ' ', $slug)),
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Lokasi program bebas',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => $rencana,
            'realisasi_anggaran' => $realisasi,
            'tujuan_program' => 'Tujuan',
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }
}
