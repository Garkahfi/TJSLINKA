<?php

namespace Tests\Unit;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Services\Pumk\PiutangCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumkPiutangCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_combines_opening_balance_and_monthly_payments(): void
    {
        $pinjaman = $this->pinjaman([
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
        ]);
        PumkSaldoAwal::create([
            'pinjaman_id' => $pinjaman->id,
            'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 200_000,
            'bunga_masuk' => 20_000,
            'denda' => 5_000,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-01-01',
            'pokok' => 100_000,
            'bunga' => 10_000,
            'denda' => 2_000,
        ]);

        $hasil = app(PiutangCalculator::class)->hitungUntukPinjaman($pinjaman);

        $this->assertSame('300000.00', $hasil['total_pokok_masuk']);
        $this->assertSame('30000.00', $hasil['total_bunga_masuk']);
        $this->assertSame('7000.00', $hasil['total_denda_masuk']);
        $this->assertSame('700000.00', $hasil['sisa_pokok']);
        $this->assertSame('70000.00', $hasil['sisa_bunga']);
        $this->assertSame('770000.00', $hasil['total_sisa']);
    }

    public function test_imported_collectibility_baseline_is_not_replaced_by_an_estimate(): void
    {
        $pinjaman = $this->pinjaman([
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'bulan_tunggakan' => 10,
            'nilai_tunggakan' => 500_000,
            'kolektibilitas' => 'macet',
            'sisa_pokok' => 900_000,
            'sisa_bunga' => 90_000,
            'source_updated_at' => '2026-07-31 00:00:00',
        ]);

        $hasil = app(PiutangCalculator::class)->hitungUntukPinjaman(
            $pinjaman,
            CarbonImmutable::parse('2026-07-31'),
        );

        $this->assertTrue($hasil['menggunakan_baseline_sumber']);
        $this->assertSame(10, $hasil['bulan_tunggakan']);
        $this->assertSame('500000.00', $hasil['nilai_tunggakan']);
        $this->assertSame('macet', $hasil['kolektibilitas']);
        $this->assertSame('990000.00', $hasil['total_sisa']);
    }

    public function test_manual_loan_uses_the_documented_estimate_until_business_rule_is_confirmed(): void
    {
        $pinjaman = $this->pinjaman([
            'pinjaman_pokok' => 1_200_000,
            'pinjaman_bunga' => 0,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-12-01',
            'nilai_angsuran_bulanan' => 100_000,
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-01-01',
            'pokok' => 100_000,
            'bunga' => 0,
            'denda' => 0,
        ]);

        $hasil = app(PiutangCalculator::class)->hitungUntukPinjaman(
            $pinjaman,
            CarbonImmutable::parse('2026-04-30'),
        );

        $this->assertFalse($hasil['menggunakan_baseline_sumber']);
        $this->assertSame(3, $hasil['bulan_tunggakan']);
        $this->assertSame('300000.00', $hasil['nilai_tunggakan']);
        $this->assertSame('kurang_lancar', $hasil['kolektibilitas']);
    }

    public function test_manual_payment_after_import_snapshot_updates_the_displayed_baseline(): void
    {
        $pinjaman = $this->pinjaman([
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'nilai_angsuran_bulanan' => 100_000,
            'bulan_tunggakan' => 5,
            'nilai_tunggakan' => 500_000,
            'kolektibilitas' => 'kurang_lancar',
            'sisa_pokok' => 800_000,
            'sisa_bunga' => 80_000,
            'source_updated_at' => '2026-07-31 12:00:00',
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-08-01',
            'pokok' => 100_000,
            'bunga' => 10_000,
            'denda' => 0,
            'created_at' => '2026-08-02 08:00:00',
            'updated_at' => '2026-08-02 08:00:00',
        ]);

        $hasil = app(PiutangCalculator::class)->hitungUntukPinjaman($pinjaman->refresh());

        $this->assertSame('700000.00', $hasil['sisa_pokok']);
        $this->assertSame('70000.00', $hasil['sisa_bunga']);
        $this->assertSame('390000.00', $hasil['nilai_tunggakan']);
        $this->assertSame(4, $hasil['bulan_tunggakan']);
        $this->assertSame('kurang_lancar', $hasil['kolektibilitas']);
        $this->assertSame(1, $hasil['jumlah_angsuran_manual_setelah_snapshot']);
    }

    private function pinjaman(array $overrides): PumkPinjaman
    {
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Uji Kalkulator',
            'source_key' => hash('sha256', 'mitra-uji-kalkulator-'.uniqid()),
        ]);

        return PumkPinjaman::create(array_merge([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'pinjaman-uji-kalkulator-'.uniqid()),
        ], $overrides));
    }
}
