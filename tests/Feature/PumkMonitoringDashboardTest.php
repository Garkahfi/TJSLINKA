<?php

namespace Tests\Feature;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriMitra;
use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\PumkBriSnapshotBulanan;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSektorUsaha;
use App\Models\PumkSnapshotBulanan;
use App\Models\PumkWilayah;
use App\Models\User;
use App\Services\Monitoring\PumkBriGeoReference;
use App\Services\Monitoring\PumkDashboardService;
use App\Services\Monitoring\PumkSnapshotService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PumkMonitoringDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_dashboard_uses_only_active_loans_and_active_partners(): void
    {
        [$mitra, $sektor] = $this->mitra('Perdagangan');
        $this->loan($mitra, 'aktif', 1000, 100, 400, 'Lancar');
        $this->loan($mitra, 'nonaktif', 9000, 1000, 5000, 'Macet', false);

        $inactiveMitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Nonaktif',
            'sektor_usaha_id' => $sektor->id,
            'source_key' => hash('sha256', 'mitra-nonaktif'),
            'is_active' => false,
        ]);
        $this->loan($inactiveMitra, 'mitra-nonaktif', 5000, 0, 1000, 'Macet');

        $data = app(PumkDashboardService::class)->live();

        $this->assertSame(1100.0, $data['total_pinjaman']);
        $this->assertSame(400.0, $data['total_sisa']);
        $this->assertSame(700.0, $data['total_realisasi']);
        $this->assertSame(1000.0, $data['saldo_pokok']);
        $this->assertSame(100.0, $data['saldo_bunga']);
        $this->assertSame(400.0, $data['total_saldo_piutang']);
        $this->assertSame(1, $data['total_binaan']);
        $this->assertSame(1, $data['jumlah_mitra']);
        $this->assertSame(1, $data['jumlah_pinjaman']);
        $this->assertSame('Perdagangan', $data['sektor']->first()['label']);
        $this->assertSame(400.0, $data['sektor']->first()['nilai']);
        $this->assertSame('Lancar', $data['kolektibilitas']->first()['label']);
        $this->assertSame('Belum Ditentukan', $data['sebaran_provinsi']->first()['label']);
    }

    public function test_monthly_snapshot_is_idempotent_and_keeps_first_value_for_period(): void
    {
        [$mitra] = $this->mitra('Jasa');
        $loan = $this->loan($mitra, 'snapshot', 1000, 0, 350, 'Kurang Lancar');
        $at = Carbon::create(2026, 9, 1, 1, 0, 0, 'Asia/Jakarta');

        $first = app(PumkSnapshotService::class)->capture($at);
        $loan->update(['total_sisa' => 100]);
        $second = app(PumkSnapshotService::class)->capture($at);

        $this->assertSame(['dibuat' => 2, 'sudah_ada' => 0], $first);
        $this->assertSame(['dibuat' => 0, 'sudah_ada' => 2], $second);
        $this->assertDatabaseCount('pumk_snapshot_bulanan', 2);
        $this->assertSame(
            '350.00',
            PumkSnapshotBulanan::where('tipe', 'sektor')->firstOrFail()->nilai,
        );
    }

    public function test_bri_dashboard_uses_the_latest_detailed_snapshot_for_selected_year(): void
    {
        PumkBriRkaTahunan::create([
            'tahun' => 2026,
            'nominal_rka' => 1000000,
        ]);
        PumkBriPenyaluranBulanan::create(['tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => 600000]);
        $mitra = PumkBriMitra::create([
            'nama_mitra' => 'Mitra Snapshot',
            'alamat' => 'Madiun',
            'wilayah' => 'Madiun',
            'sektor_usaha' => 'Jasa',
            'pinjaman' => 600000,
            'tanggal_pencairan' => '2026-01-10',
            'tanggal_jatuh_tempo' => '2027-01-10',
            'source_key' => hash('sha256', 'mitra-snapshot'),
        ]);
        $fasilitas = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => '6605ca5a-c922-4b3b-971d-4d5e5c74bf6f',
            'pinjaman' => 600000,
            'tanggal_pencairan' => '2026-01-10',
        ]);
        foreach ([[1, 500000, 'L', 'Lancar'], [2, 400000, 'KL', 'Kurang Lancar']] as $snapshot) {
            PumkBriSnapshotBulanan::create([
                'mitra_id' => $mitra->id,
                'fasilitas_id' => $fasilitas->id,
                'bulan' => $snapshot[0],
                'tahun' => 2026,
                'saldo_piutang' => $snapshot[1],
                'kolektibilitas_kode' => $snapshot[2],
                'kolektibilitas_label' => $snapshot[3],
                'source_sheet' => $snapshot[0] === 1 ? 'Jan' : 'Feb',
                'source_row' => 4,
                'nama_mitra_sumber' => 'Mitra Snapshot*',
                'alamat_sumber' => 'Madiun',
                'wilayah_sumber' => 'Madiun',
                'sektor_usaha_sumber' => 'Jasa',
                'pinjaman_sumber' => 600000,
                'tanggal_pencairan_sumber' => '2026-01-10',
                'profil_sumber_terverifikasi' => true,
            ]);
        }

        $data = app(PumkDashboardService::class)->bri(2026);

        $this->assertSame(2026, $data['year']);
        $this->assertSame(2, $data['latest_month']);
        $this->assertSame('Februari', $data['latest_month_label']);
        $this->assertSame(1000000.0, $data['ringkasan']['rka']);
        $this->assertSame(600000.0, $data['ringkasan']['realisasi']);
        $this->assertSame(60.0, $data['ringkasan']['progres']);
        $this->assertSame(400000.0, $data['ringkasan']['outstanding']);
        $this->assertSame(1, $data['ringkasan']['jumlah_mitra']);
        $this->assertSame([
            'snapshot_terverifikasi' => 2,
            'snapshot_belum_terverifikasi' => 0,
            'identitas_menunggu_review' => 0,
            'snapshot_terbaru_belum_terverifikasi' => 0,
            'identitas_final' => true,
            'breakdown_terverifikasi' => true,
        ], $data['verifikasi']);
        $this->assertSame([
            'snapshot' => true,
            'rka' => true,
            'realisasi' => true,
            'realisasi_source' => 'input_bulanan',
            'wilayah_mitra_dapat_berulang' => false,
        ], $data['ketersediaan']);
        $this->assertSame('Jasa', $data['sektor']->first()['label']);
        $this->assertSame(400000.0, $data['sektor']->first()['nilai']);
        $this->assertSame(1, $data['kualitas']->firstWhere('kode', 'KL')['jumlah']);
        $this->assertSame('Madiun', $data['wilayah']->first()['nama']);
        $this->assertSame('madiun', $data['wilayah']->first()['geo_key']);
        $this->assertTrue($data['wilayah']->first()['geo_mapped']);
        $this->assertSame(1, $data['wilayah']->first()['jumlah_fasilitas']);
        $this->assertSame(1, $data['wilayah']->first()['kurang_lancar']);
        $this->assertSame(2, $data['peta']['bulan_snapshot']);
        $this->assertSame('Februari', $data['peta']['bulan_snapshot_label']);
        $this->assertSame(1, $data['peta']['total_mitra_snapshot']);
        $this->assertSame(0, $data['peta']['unmapped_wilayah']);
        $this->assertSame([500000.0, 400000.0], $data['tren_outstanding']->pluck('nilai')->all());
        $this->assertSame(12, $data['rka_bulanan']->count());
    }

    public function test_bri_dashboard_counts_a_mitra_once_even_when_it_has_two_facilities(): void
    {
        $mitra = PumkBriMitra::create([
            'nama_mitra' => 'Mitra Multi Fasilitas*',
            'alamat' => 'Jl. Sumber 1',
            'wilayah' => 'Kota Madiun',
            'sektor_usaha' => 'Perdagangan',
            'source_key' => hash('sha256', 'mitra-multi-fasilitas'),
        ]);
        $fasilitasA = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => 'e567c41f-824e-4b3e-9678-8f2d5c942c4e',
            'pinjaman' => 100000,
            'tenor_raw' => '12 M',
            'tanggal_pencairan' => '2026-01-10',
        ]);
        $fasilitasB = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => 'd7733c0d-b30a-4db1-89da-842c3d4d90ae',
            'pinjaman' => 200000,
            'tenor_raw' => '24 M',
            'tanggal_pencairan' => '2026-02-11',
        ]);
        foreach ([[$fasilitasA, 90000, 'Kota Madiun'], [$fasilitasB, 180000, 'Kab. Magetan']] as [$fasilitas, $saldo, $wilayah]) {
            PumkBriSnapshotBulanan::create([
                'mitra_id' => $mitra->id,
                'fasilitas_id' => $fasilitas->id,
                'bulan' => 2,
                'tahun' => 2026,
                'saldo_piutang' => $saldo,
                'kolektibilitas_kode' => 'L',
                'kolektibilitas_label' => 'Lancar',
                'source_sheet' => 'Feb',
                'source_row' => $fasilitas->id,
                'nama_mitra_sumber' => 'Mitra Multi Fasilitas*',
                'alamat_sumber' => 'Jl. Sumber 1',
                'wilayah_sumber' => $wilayah,
                'sektor_usaha_sumber' => 'Perdagangan',
                'pinjaman_sumber' => $fasilitas->pinjaman,
                'tenor_sumber' => $fasilitas->tenor_raw,
                'tanggal_pencairan_sumber' => $fasilitas->tanggal_pencairan,
                'profil_sumber_terverifikasi' => true,
            ]);
        }
        PumkBriPenyaluranBulanan::create(['tahun' => 2026, 'bulan' => 2, 'nominal_penyaluran' => 300000]);

        $data = app(PumkDashboardService::class)->bri(2026);

        $this->assertSame(1, $data['ringkasan']['jumlah_mitra']);
        $this->assertSame(270000.0, $data['ringkasan']['outstanding']);
        $this->assertSame(300000.0, $data['ringkasan']['realisasi']);
        $this->assertNull($data['ringkasan']['rka']);
        $this->assertNull($data['ringkasan']['progres']);
        $this->assertSame(2, $data['wilayah']->count());
        $this->assertTrue($data['ketersediaan']['wilayah_mitra_dapat_berulang']);
        $this->assertSame(1, $data['peta']['total_mitra_snapshot']);
        $this->assertSame(2, $data['wilayah']->sum('jumlah_fasilitas'));
        $this->assertSame(['kota madiun', 'magetan'], $data['wilayah']->pluck('geo_key')->sort()->values()->all());
        $this->assertTrue($data['wilayah']->every(fn (array $region): bool => $region['geo_mapped']));
    }

    public function test_bri_dashboard_allows_a_summary_only_year_and_falls_back_per_year(): void
    {
        $mitra = PumkBriMitra::create([
            'nama_mitra' => 'Mitra Tahun 2026',
            'alamat' => 'Madiun',
            'wilayah' => 'Kota Madiun',
            'sektor_usaha' => 'Jasa',
            'source_key' => hash('sha256', 'mitra-tahun-2026'),
        ]);
        PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => '5aeac3e0-6c07-4f20-9a98-587965655ce4',
            'pinjaman' => 600000,
            'tanggal_pencairan' => '2026-01-10',
        ]);
        PumkBriRkaTahunan::create([
            'tahun' => 2027,
            'nominal_rka' => 1000000,
        ]);
        PumkBriPenyaluranBulanan::create(['tahun' => 2027, 'bulan' => 1, 'nominal_penyaluran' => 250000]);

        $data = app(PumkDashboardService::class)->bri(2027);

        $this->assertSame(2027, $data['year']);
        $this->assertSame([2027], $data['years']->all());
        $this->assertSame(0, $data['latest_month']);
        $this->assertSame(1000000.0, $data['ringkasan']['rka']);
        $this->assertSame(250000.0, $data['ringkasan']['realisasi']);
        $this->assertSame(25.0, $data['ringkasan']['progres']);
        $this->assertSame(0, $data['ringkasan']['jumlah_mitra']);
        $this->assertSame([
            'snapshot' => false,
            'rka' => true,
            'realisasi' => true,
            'realisasi_source' => 'input_bulanan',
            'wilayah_mitra_dapat_berulang' => false,
        ], $data['ketersediaan']);
        $this->assertFalse($data['verifikasi']['identitas_final']);
        $this->assertFalse($data['verifikasi']['breakdown_terverifikasi']);
    }

    public function test_rka_without_monthly_input_is_zero_realisasi_and_zero_progress(): void
    {
        PumkBriRkaTahunan::create(['tahun' => 2028, 'nominal_rka' => 175000000]);

        $data = app(PumkDashboardService::class)->bri(2028);

        $this->assertSame(175000000.0, $data['ringkasan']['rka']);
        $this->assertSame(0.0, $data['ringkasan']['realisasi']);
        $this->assertSame(0.0, $data['ringkasan']['progres']);
        $this->assertTrue($data['ketersediaan']['rka']);
        $this->assertTrue($data['ketersediaan']['realisasi']);
        $this->assertTrue($data['rka_bulanan']->every(fn (array $month): bool => $month['nilai'] === null));
    }

    public function test_public_home_contains_bri_cache_and_live_pumk_dashboard(): void
    {
        $user = User::factory()->create();
        [$mitra] = $this->mitra('Pertanian');
        $this->loan($mitra, 'home', 2000, 0, 500, 'Lancar');
        $briMitra = PumkBriMitra::create([
            'nama_mitra' => 'Mitra Wilayah Baru',
            'wilayah' => 'Wilayah Uji Tidak Dikenal',
            'sektor_usaha' => 'Jasa',
            'source_key' => hash('sha256', 'mitra-wilayah-baru'),
        ]);
        $briFasilitas = PumkBriFasilitas::create([
            'mitra_id' => $briMitra->id,
            'reference_key' => 'cc4b0fba-23e0-467a-b5da-915a9b974006',
            'pinjaman' => 100000,
        ]);
        PumkBriSnapshotBulanan::create([
            'mitra_id' => $briMitra->id,
            'fasilitas_id' => $briFasilitas->id,
            'bulan' => 8,
            'tahun' => 2026,
            'saldo_piutang' => 80000,
            'kolektibilitas_kode' => 'L',
            'kolektibilitas_label' => 'Lancar',
            'source_sheet' => 'Agst',
            'source_row' => 4,
            'nama_mitra_sumber' => 'Mitra Wilayah Baru',
            'wilayah_sumber' => 'Wilayah Uji Tidak Dikenal',
            'sektor_usaha_sumber' => 'Jasa',
            'pinjaman_sumber' => 100000,
            'profil_sumber_terverifikasi' => true,
        ]);

        $this->actingAs($user, 'web')->get('/')
            ->assertOk()
            ->assertSee('Dashboard PUMK PT. INKA (Persero)')
            ->assertSee('Program PUMK PT. INKA (Persero)')
            ->assertSee('Saldo Piutang Pinjaman (Pokok)')
            ->assertSee('Saldo Piutang Pinjaman (Bunga)')
            ->assertSee('Total Saldo Piutang Pinjaman')
            ->assertSee('Total Binaan')
            ->assertSee('Sektor Ekonomi Portofolio Mitra Binaan')
            ->assertSee('Kualitas Piutang Mitra Binaan')
            ->assertSee('Sebaran PUMK per Provinsi')
            ->assertSee('Sebaran Penyaluran Dana PUMK (BRI)')
            ->assertSee('data-pumk-map-state', false)
            ->assertSee('data-pumk-region-row', false)
            ->assertSee('Fasilitas')
            ->assertSee('Wilayah Uji Tidak Dikenal')
            ->assertSee('1 wilayah belum memiliki pasangan geometri dan tetap tercatat di tabel.')
            ->assertSee('indonesia-kabupaten-kota.geojson')
            ->assertDontSee('Koordinat geografis belum tersedia pada sumber Excel')
            ->assertSee('id="pumk-live-sector-chart"', false)
            ->assertSee('id="pumk-live-quality-chart"', false)
            ->assertSee('id="pumk-live-province-chart"', false)
            ->assertSee('Rp2.000')
            ->assertSee('Rp500');
    }

    public function test_bri_geo_reference_preserves_city_and_regency_distinction(): void
    {
        $reference = app(PumkBriGeoReference::class);

        $this->assertSame('madiun', $reference->normalize('Kabupaten Madiun'));
        $this->assertSame('madiun', $reference->normalize('Madiun Kab.'));
        $this->assertSame('kota madiun', $reference->normalize('Kota Madiun'));
        $this->assertTrue($reference->isMapped('Kab. Madiun'));
        $this->assertTrue($reference->isMapped('Kota Madiun'));
        $this->assertFalse($reference->isMapped('Wilayah Uji Tidak Dikenal'));
    }

    public function test_live_dashboard_groups_internal_portfolio_by_province(): void
    {
        $centralJava = PumkWilayah::create(['nama' => 'Wonogiri', 'slug' => 'wonogiri', 'is_active' => true]);
        $eastJava = PumkWilayah::create(['nama' => 'Kab. Madiun', 'slug' => 'kab-madiun', 'is_active' => true]);
        [$mitraCentral] = $this->mitra('Industri');
        [$mitraEast] = $this->mitra('Jasa');
        $mitraCentral->update(['wilayah_id' => $centralJava->id, 'wilayah_sumber' => 'Wonogiri']);
        $mitraEast->update(['wilayah_id' => $eastJava->id, 'wilayah_sumber' => 'Kab. Madiun']);
        $this->loan($mitraCentral, 'jateng', 1000, 100, 600, 'Lancar');
        $this->loan($mitraEast, 'jatim', 2000, 200, 900, 'Lancar');

        $regions = app(PumkDashboardService::class)->live()['sebaran_provinsi'];

        $this->assertSame(900.0, $regions->firstWhere('label', 'Jawa Timur')['nilai']);
        $this->assertSame(600.0, $regions->firstWhere('label', 'Jawa Tengah')['nilai']);
        $this->assertSame(1, $regions->firstWhere('label', 'Jawa Timur')['jumlah']);
        $this->assertSame(1, $regions->firstWhere('label', 'Jawa Tengah')['jumlah']);
    }

    public function test_snapshot_command_is_scheduled_monthly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'pumk:snapshot-bulanan'));

        $this->assertNotNull($event);
        $this->assertSame('0 1 1 * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
    }

    /** @return array{PumkMitra, PumkSektorUsaha} */
    private function mitra(string $sectorName): array
    {
        $sector = PumkSektorUsaha::create([
            'nama' => $sectorName,
            'slug' => strtolower($sectorName),
            'is_active' => true,
        ]);
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra '.$sectorName,
            'sektor_usaha_id' => $sector->id,
            'source_key' => hash('sha256', 'mitra-'.$sectorName),
            'is_active' => true,
        ]);

        return [$mitra, $sector];
    }

    private function loan(
        PumkMitra $mitra,
        string $key,
        float $principal,
        float $interest,
        float $remaining,
        string $quality,
        bool $active = true,
    ): PumkPinjaman {
        return PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'pinjaman_pokok' => $principal,
            'pinjaman_bunga' => $interest,
            'total_sisa' => $remaining,
            'kolektibilitas' => $quality,
            'source_key' => hash('sha256', 'pinjaman-'.$key),
            'is_active' => $active,
        ]);
    }
}
