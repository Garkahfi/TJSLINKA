<?php

namespace Tests\Unit;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Services\Pumk\PiutangCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
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

    public function test_opening_balance_is_included_on_its_cutoff_calendar_date(): void
    {
        $loan = $this->pinjaman(['pinjaman_pokok' => '1000000.00', 'pinjaman_bunga' => '0.00']);
        PumkSaldoAwal::create([
            'pinjaman_id' => $loan->id, 'cutoff_date' => '2025-12-31',
            'pokok_masuk' => '200000.00', 'bunga_masuk' => '0.00', 'denda' => '0.00',
        ]);

        $calculator = app(PiutangCalculator::class);
        $this->assertSame('1000000.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2025-12-30', 'Asia/Jakarta'))['total_sisa']);
        $this->assertSame('800000.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2025-12-31', 'Asia/Jakarta'))['total_sisa']);
    }

    public function test_imported_loan_with_missing_schedule_is_not_assumed_lancar(): void
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
        $this->assertNull($hasil['bulan_tunggakan']);
        $this->assertNull($hasil['nilai_tunggakan']);
        $this->assertNull($hasil['kolektibilitas']);
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

    public function test_imported_payment_changes_balance_and_recalculates_from_raw_arrears(): void
    {
        $pinjaman = $this->pinjaman([
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'mulai_angsuran' => '2026-01-01',
            'nilai_angsuran_bulanan' => 100_000,
            'bulan_tunggakan' => 4,
            'nilai_tunggakan' => 400_000,
            'kolektibilitas' => 'kurang_lancar',
            'sisa_pokok' => 800_000,
            'sisa_bunga' => 80_000,
            'source_updated_at' => '2026-07-31 12:00:00',
        ]);
        PumkSaldoAwal::create([
            'pinjaman_id' => $pinjaman->id, 'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 200_000, 'bunga_masuk' => 20_000, 'denda' => 0,
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

        $hasil = app(PiutangCalculator::class)->hitungUntukPinjaman($pinjaman->refresh(), CarbonImmutable::parse('2026-08-31'));

        $this->assertSame('700000.00', $hasil['sisa_pokok']);
        $this->assertSame('70000.00', $hasil['sisa_bunga']);
        $this->assertSame('400000.00', $hasil['nilai_tunggakan']);
        $this->assertSame('470000.00', $hasil['tunggakan_mentah']);
        $this->assertSame(4, $hasil['bulan_tunggakan']);
        $this->assertSame('kurang_lancar', $hasil['kolektibilitas']);
        $this->assertSame(1, $hasil['jumlah_angsuran_manual_setelah_snapshot']);
    }

    public function test_decimal_payments_keep_cents_beyond_float_precision(): void
    {
        // In-memory models avoid SQLite numeric affinity becoming the subject of this test.
        $loan = new PumkPinjaman([
            'pinjaman_pokok' => '9007199254740991.10', 'pinjaman_bunga' => '0.00',
        ]);
        $loan->setRelation('saldoAwal', null);
        $loan->setRelation('angsuran', new Collection([
            new PumkAngsuran(['pokok' => '9007199254740990.66', 'bunga' => '0.00', 'denda' => '0.00']),
            new PumkAngsuran(['pokok' => '0.11', 'bunga' => '0.00', 'denda' => '0.00']),
        ]));

        $result = app(PiutangCalculator::class)->hitungUntukPinjaman($loan);

        $this->assertSame('9007199254740990.77', $result['total_pokok_masuk']);
        $this->assertSame('0.33', $result['total_sisa']);
    }

    public function test_manual_denda_does_not_reduce_principal_interest_or_arrears(): void
    {
        $loan = $this->pinjaman([
            'pinjaman_pokok' => '1200000.00', 'pinjaman_bunga' => '0.00',
            'mulai_angsuran' => '2026-01-01', 'selesai_angsuran' => '2026-12-01',
            'nilai_angsuran_bulanan' => '100000.00',
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $loan->id, 'periode' => '2026-02-01',
            'pokok' => '0.00', 'bunga' => '0.00', 'denda' => '100000.00',
        ]);

        $result = app(PiutangCalculator::class)->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2026-02-28'));

        $this->assertSame('1200000.00', $result['total_sisa']);
        $this->assertSame('100000.00', $result['total_denda_masuk']);
        $this->assertSame('200000.00', $result['tunggakan_mentah']);
        $this->assertSame(2, $result['bulan_tunggakan']);
        $this->assertSame('kurang_lancar', $result['kolektibilitas']);
    }

    public function test_imported_payment_edit_uses_net_delta_and_as_of_excludes_later_periods(): void
    {
        $loan = $this->pinjaman([
            'pinjaman_pokok' => '1200.00', 'pinjaman_bunga' => '0.00',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '1000.00', 'sisa_bunga' => '0.00',
                'bulan_tunggakan' => 2, 'nilai_tunggakan' => '200.00',
                'kolektibilitas' => 'kurang_lancar',
                'total_pokok_masuk' => '200.00', 'total_bunga_masuk' => '0.00',
                'total_denda_masuk' => '0.00',
            ],
        ]);
        PumkSaldoAwal::create([
            'pinjaman_id' => $loan->id, 'cutoff_date' => '2025-12-31',
            'pokok_masuk' => '200.00', 'bunga_masuk' => '0.00', 'denda' => '0.00',
        ]);
        $payment = PumkAngsuran::create([
            'pinjaman_id' => $loan->id, 'periode' => '2026-08-01',
            'pokok' => '100.00', 'bunga' => '0.00', 'denda' => '0.00',
            'created_at' => '2026-08-02 08:00:00',
        ]);
        $calculator = app(PiutangCalculator::class);
        $this->assertSame('1000.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2026-07-31'))['total_sisa']);
        $this->assertSame('900.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2026-08-31'))['total_sisa']);

        $payment->update(['pokok' => '150.00']);
        $this->assertSame('850.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2026-08-31'))['total_sisa']);
        $payment->update(['pokok' => '80.00']);
        $this->assertSame('920.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2026-08-31'))['total_sisa']);
        $this->assertSame('920.00', $calculator->hitungUntukPinjaman($loan->fresh(), CarbonImmutable::parse('2026-08-31'))['total_sisa']);
    }

    public function test_imported_position_before_source_snapshot_is_not_guessed(): void
    {
        $loan = $this->pinjaman([
            'source_updated_at' => '2026-07-31 23:59:59',
            'pinjaman_pokok' => '1000.00', 'pinjaman_bunga' => '0.00',
        ]);

        $this->expectException(LogicException::class);
        app(PiutangCalculator::class)->hitungUntukPinjaman($loan, CarbonImmutable::parse('2026-06-30'));
    }

    public function test_spj_metadata_and_upload_path_do_not_change_financial_position(): void
    {
        $loan = new PumkPinjaman([
            'pinjaman_pokok' => '1200000.00', 'pinjaman_bunga' => '0.00',
            'mulai_angsuran' => '2026-01-01', 'nilai_angsuran_bulanan' => '100000.00',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '1200000.00', 'sisa_bunga' => '0.00',
                'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00',
                'formula_sumber' => [
                    'mulai_angsuran' => '2026-01-01', 'angsuran_bulanan' => '100000.00',
                    'total_kewajiban' => '1200000.00',
                ],
            ],
        ]);
        $loan->setRelation('saldoAwal', null);
        $loan->setRelation('angsuran', new Collection);
        $calculator = app(PiutangCalculator::class);
        $before = $calculator->hitungUntukPinjaman($loan, CarbonImmutable::parse('2026-08-31'));

        $loan->spj_awal = 'SPJ-REVISI';
        $loan->reschedule_ke1 = 'RESCHEDULE-REVISI';
        $loan->berkas_spj_path = 'dokumen/baru.pdf';
        $after = $calculator->hitungUntukPinjaman($loan, CarbonImmutable::parse('2026-08-31'));

        foreach (['sisa_pokok', 'sisa_bunga', 'total_sisa', 'tunggakan_mentah', 'bulan_tunggakan', 'kolektibilitas'] as $field) {
            $this->assertSame($before[$field], $after[$field]);
        }
    }

    public function test_imported_arrears_are_based_on_source_due_not_rounded_arrears(): void
    {
        $loan = new PumkPinjaman([
            'pinjaman_pokok' => '1000000.00', 'pinjaman_bunga' => '0.00',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '1000000.00', 'sisa_bunga' => '0.00',
                'bulan_tunggakan' => 3, 'nilai_tunggakan' => '300000.00',
                'kolektibilitas' => 'kurang_lancar', 'total_pokok_masuk' => '0.00',
                'total_bunga_masuk' => '0.00', 'total_denda_masuk' => '0.00',
                'formula_sumber' => [
                    'mulai_angsuran' => '2026-05-01', 'angsuran_bulanan' => '100000.00',
                    'total_kewajiban' => '1000000.00', 'jumlah_jatuh_tempo' => 3,
                    'jatuh_tempo_nominal' => '300000.00', 'total_pokok_bunga_masuk' => '0.00',
                    'tanggal_acuan' => '2026-07-31',
                ],
            ],
        ]);
        $loan->setRelation('saldoAwal', null);
        $loan->setRelation('angsuran', new Collection([
            new PumkAngsuran(['pokok' => '20000.00', 'bunga' => '0.00', 'denda' => '0.00']),
        ]));

        $result = app(PiutangCalculator::class)->hitungUntukPinjaman($loan, CarbonImmutable::parse('2026-07-31'));

        $this->assertSame('280000.00', $result['tunggakan_mentah']);
        $this->assertSame(2, $result['bulan_tunggakan']);
        $this->assertSame('kurang_lancar', $result['kolektibilitas']);
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
