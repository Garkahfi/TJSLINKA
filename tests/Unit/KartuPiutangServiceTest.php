<?php

namespace Tests\Unit;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Services\Pumk\KartuPiutangService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuPiutangServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_every_tenor_month_and_carries_balances_forward(): void
    {
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Jadwal',
            'source_key' => hash('sha256', 'mitra-jadwal'),
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'pinjaman-jadwal'),
            'mulai_angsuran' => '2026-01-05',
            'selesai_angsuran' => '2026-04-05',
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'nilai_angsuran_bulanan' => 275_000,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-02-01',
            'nomor_bukti' => 'BKM-002',
            'pokok' => 100_000,
            'bunga' => 10_000,
            'denda' => 5_000,
        ]);

        $kartu = app(KartuPiutangService::class)->buat(
            $pinjaman->refresh(),
            CarbonImmutable::parse('2026-02-15'),
        );

        $this->assertSame(4, $kartu['tenor']);
        $this->assertCount(4, $kartu['jadwal']);
        $this->assertSame('2026-01-05', $kartu['jadwal'][0]['tanggal']->toDateString());
        $this->assertSame('1000000.00', $kartu['jadwal'][0]['saldo_pokok']);
        $this->assertSame('BKM-002', $kartu['jadwal'][1]['nomor_bukti']);
        $this->assertTrue($kartu['jadwal'][1]['has_payment']);
        $this->assertFalse($kartu['jadwal'][0]['has_payment']);
        $this->assertNull($kartu['jadwal'][1]['editable_angsuran']);
        $this->assertSame('110000.00', $kartu['jadwal'][1]['total_dibayar']);
        $this->assertSame('900000.00', $kartu['jadwal'][1]['saldo_pokok']);
        $this->assertSame('90000.00', $kartu['jadwal'][3]['saldo_bunga']);
        $this->assertTrue($kartu['jadwal'][1]['is_bulan_berjalan']);
        $this->assertSame('990000.00', $kartu['kekurangan']);
        $this->assertSame('5000.00', $kartu['denda']);
    }

    public function test_invalid_date_range_is_reported_without_hiding_recorded_payments(): void
    {
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Tanggal Terbalik',
            'source_key' => hash('sha256', 'mitra-tanggal-terbalik'),
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'pinjaman-tanggal-terbalik'),
            'mulai_angsuran' => '2026-12-01',
            'selesai_angsuran' => '2026-01-01',
            'pinjaman_pokok' => 100_000,
            'pinjaman_bunga' => 10_000,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-02-01',
            'pokok' => 10_000,
            'bunga' => 1_000,
        ]);

        $kartu = app(KartuPiutangService::class)->buat($pinjaman->refresh());

        $this->assertNull($kartu['tenor']);
        $this->assertNotNull($kartu['jadwal_error']);
        $this->assertCount(1, $kartu['jadwal']);
        $this->assertSame('Pembayaran di luar tenor', $kartu['jadwal'][0]['catatan']);
    }

    public function test_historical_payment_expands_the_schedule_with_every_month_in_between(): void
    {
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Histori Panjang',
            'source_key' => hash('sha256', 'mitra-histori-panjang'),
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'pinjaman-histori-panjang'),
            'mulai_angsuran' => '2026-01-05',
            'selesai_angsuran' => '2026-03-05',
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'nilai_angsuran_bulanan' => 366_667,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2015-12-01',
            'nomor_bukti' => 'BKM-HISTORI-2015',
            'pokok' => 100_000,
            'bunga' => 10_000,
        ]);

        $kartu = app(KartuPiutangService::class)->buat(
            $pinjaman->refresh(),
            CarbonImmutable::parse('2026-09-10'),
        );

        $this->assertCount(124, $kartu['jadwal']);
        $this->assertSame('2015-12-01', $kartu['jadwal'][0]['tanggal']->toDateString());
        $this->assertSame('BKM-HISTORI-2015', $kartu['jadwal'][0]['nomor_bukti']);
        $this->assertTrue($kartu['jadwal'][0]['is_historis']);
        $this->assertSame('2016-01-01', $kartu['jadwal'][1]['tanggal']->toDateString());
        $this->assertFalse($kartu['jadwal'][1]['has_payment']);
        $this->assertSame('2026-01-05', $kartu['jadwal'][121]['tanggal']->toDateString());
        $this->assertSame(1, $kartu['jadwal'][121]['no']);
        $this->assertSame('2026-03-05', $kartu['jadwal'][123]['tanggal']->toDateString());
        $this->assertTrue($kartu['has_history']);
    }

    public function test_year_filter_keeps_full_timeline_carry_forward_and_empty_month_rows(): void
    {
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Filter Tahun', 'source_key' => hash('sha256', 'mitra-filter-tahun')]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'pinjaman-filter-tahun'),
            'mulai_angsuran' => '2025-11-01',
            'selesai_angsuran' => '2026-02-01',
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2025-12-01',
            'pokok' => 100_000, 'bunga' => 10_000,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2026-01-01',
            'pokok' => 50_000, 'bunga' => 5_000,
        ]);
        $service = app(KartuPiutangService::class);

        $latest = $service->buat($pinjaman->refresh(), tahun: 'terbaru');
        $this->assertSame([2026, 2025], $latest['tahun_tersedia']);
        $this->assertSame(2026, $latest['tahun_terpilih']);
        $this->assertCount(2, $latest['jadwal']);
        $this->assertSame('850000.00', $latest['jadwal'][0]['saldo_pokok']);
        $this->assertSame('85000.00', $latest['jadwal'][0]['saldo_bunga']);

        $all = $service->buat($pinjaman->refresh(), tahun: 'semua');
        $this->assertCount(4, $all['jadwal']);

        PumkSaldoAwal::create([
            'pinjaman_id' => $pinjaman->id, 'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 0, 'bunga_masuk' => 0, 'denda' => 0,
        ]);
        $old = $service->buat($pinjaman->refresh(), tahun: 2025);
        $this->assertArrayNotHasKey('status_histori', $old);
        $this->assertCount(2, $old['jadwal']);
        $this->assertSame('2025-11-01', $old['jadwal'][0]['tanggal']->toDateString());
        $this->assertFalse($old['jadwal'][0]['has_payment']);
        $this->assertNull($old['jadwal'][0]['pokok_dibayar']);
        $this->assertSame('2025-12-01', $old['jadwal'][1]['tanggal']->toDateString());
        $this->assertDatabaseCount('pumk_angsuran', 2);

        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2027-03-01',
            'pokok' => 25000, 'bunga' => 2500,
        ]);
        $outsideTenor = $service->buat($pinjaman->refresh(), tahun: 2027);
        $this->assertContains(2027, $outsideTenor['tahun_tersedia']);
        $this->assertSame('Pembayaran di luar tenor', $outsideTenor['jadwal'][array_key_last($outsideTenor['jadwal'])]['catatan']);
    }

    public function test_empty_selected_year_displays_twelve_rows_without_creating_transactions(): void
    {
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Tahun Kosong', 'source_key' => hash('sha256', 'mitra-tahun-kosong')]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'pinjaman-tahun-kosong'),
            'mulai_angsuran' => '2024-01-01',
            'selesai_angsuran' => '2024-12-01',
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
        ]);

        $card = app(KartuPiutangService::class)->buat($pinjaman, tahun: 2024);

        $this->assertCount(12, $card['jadwal']);
        $this->assertSame('2024-01-01', $card['jadwal'][0]['tanggal']->toDateString());
        $this->assertSame('2024-12-01', $card['jadwal'][11]['tanggal']->toDateString());
        $this->assertFalse($card['jadwal'][0]['has_payment']);
        $this->assertNull($card['jadwal'][0]['total_dibayar']);
        $this->assertSame('1000000.00', $card['jadwal'][11]['saldo_pokok']);
        $this->assertDatabaseCount('pumk_angsuran', 0);
    }
}
