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

    public function test_official_date_does_not_follow_the_computer_clock_and_payment_changes_category(): void
    {
        $loan = new PumkPinjaman([
            'pinjaman_pokok' => '25000000.00', 'pinjaman_bunga' => '2250000.00',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '17706900.00', 'sisa_bunga' => '1587500.00',
                'total_pokok_masuk' => '7293100.00', 'total_bunga_masuk' => '662500.00',
                'formula_sumber' => [
                    'tanggal_acuan' => '2026-07-31', 'mulai_angsuran' => '2025-01-01',
                    'angsuran_bulanan' => '756950', 'total_kewajiban' => '27250000',
                    'pokok_masuk_raw' => '7293100', 'bunga_masuk_raw' => '662500',
                    'sisa_pokok_raw' => '17706900', 'sisa_bunga_raw' => '1587500',
                    'total_sisa_raw' => '19294400',
                ],
            ],
        ]);
        $loan->setRelation('saldoAwal', new PumkSaldoAwal([
            'cutoff_date' => '2025-12-31', 'pokok_masuk' => '7293100.00',
            'bunga_masuk' => '662500.00', 'denda' => '0.00',
        ]));
        $loan->setRelation('angsuran', new Collection);
        $calculator = app(PiutangCalculator::class);

        foreach (['2026-07-31', '2026-10-01', '2026-10-03'] as $clock) {
            CarbonImmutable::setTestNow($clock);
            $result = $calculator->hitungUntukPinjaman($loan);
            $this->assertSame('2026-07-31', $result['tanggal_acuan']);
            $this->assertSame(19, $result['jumlah_jatuh_tempo']);
            $this->assertSame(8, $result['bulan_tunggakan']);
            $this->assertSame('diragukan', $result['kolektibilitas']);
        }
        CarbonImmutable::setTestNow();

        $later = $calculator->hitungUntukPinjaman($loan, CarbonImmutable::parse('2026-10-01'));
        $this->assertSame(22, $later['jumlah_jatuh_tempo']);
        $this->assertSame(11, $later['bulan_tunggakan']);
        $this->assertSame('macet', $later['kolektibilitas']);

        $loan->setRelation('angsuran', new Collection([
            new PumkAngsuran([
                'periode' => '2026-08-01', 'pokok' => '1500000.00',
                'bunga' => '0.00', 'denda' => '0.00',
            ]),
        ]));
        $paid = $calculator->hitungUntukPinjaman($loan);
        $this->assertSame('2026-07-31', $paid['tanggal_acuan']);
        $this->assertSame(6, $paid['bulan_tunggakan']);
        $this->assertSame('kurang_lancar', $paid['kolektibilitas']);
        $this->assertSame('17794400.00', $paid['total_sisa']);
    }

    public function test_source_total_keeps_sub_cent_precision_until_display(): void
    {
        $loan = new PumkPinjaman([
            'pinjaman_pokok' => '15000000.00', 'pinjaman_bunga' => '1427846.22',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '1998.00', 'sisa_bunga' => '-1997.77',
                'total_pokok_masuk' => '14998002.00', 'total_bunga_masuk' => '1429844.00',
                'formula_sumber' => [
                    'tanggal_acuan' => '2026-07-31', 'mulai_angsuran' => '2022-01-01',
                    'angsuran_bulanan' => '456329.06177334', 'total_kewajiban' => '16427846.22',
                    'pokok_masuk_raw' => '14998002', 'bunga_masuk_raw' => '1429844',
                    'sisa_pokok_raw' => '1998', 'sisa_bunga_raw' => '-1997.77616',
                    'total_sisa_raw' => '0.22383972',
                ],
            ],
        ]);
        $loan->setRelation('saldoAwal', new PumkSaldoAwal([
            'cutoff_date' => '2025-12-31', 'pokok_masuk' => '14998002.00',
            'bunga_masuk' => '1429844.00', 'denda' => '0.00',
        ]));
        $loan->setRelation('angsuran', new Collection);

        $result = app(PiutangCalculator::class)->hitungUntukPinjaman($loan);
        $this->assertSame('0.22383972', $result['total_sisa_raw']);
        $this->assertSame('-1997.77616', $result['sisa_bunga_raw']);
        $this->assertSame('-1997.78', $result['sisa_bunga']);
        $this->assertSame('0.22', $result['total_sisa']);
    }

    public function test_source_139_payment_changes_aw_without_forcing_a_category_change(): void
    {
        // Independent Reg4 cells at C5: AG 48,472,200; AK 1,346,450;
        // AN 34,300,000; AP 91; AR 10; AW 14,172,200.
        $loan = new PumkPinjaman([
            'pinjaman_pokok' => '44472200.00', 'pinjaman_bunga' => '4000000.00',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '12996800.00', 'sisa_bunga' => '1175400.00',
                'total_pokok_masuk' => '31475400.00', 'total_bunga_masuk' => '2824600.00',
                'formula_sumber' => [
                    'tanggal_acuan' => '2026-07-31', 'mulai_angsuran' => '2019-01-02',
                    'angsuran_bulanan' => '1346450', 'total_kewajiban' => '48472200',
                    'pokok_masuk_raw' => '31475400', 'bunga_masuk_raw' => '2824600',
                    'sisa_pokok_raw' => '12996800', 'sisa_bunga_raw' => '1175400',
                    'total_sisa_raw' => '14172200',
                ],
            ],
        ]);
        $loan->setRelation('saldoAwal', new PumkSaldoAwal([
            'cutoff_date' => '2025-12-31', 'pokok_masuk' => '31475400.00',
            'bunga_masuk' => '2824600.00', 'denda' => '0.00',
        ]));
        $loan->setRelation('angsuran', new Collection);

        $calculator = app(PiutangCalculator::class);
        $source = $calculator->hitungUntukPinjaman($loan);
        $this->assertSame(91, $source['jumlah_jatuh_tempo']);
        $this->assertSame(10, $source['bulan_tunggakan']);
        $this->assertSame('macet', $source['kolektibilitas']);
        $this->assertSame('14172200.00', $source['total_sisa']);

        $loan->setRelation('angsuran', new Collection([
            new PumkAngsuran([
                'periode' => '2026-08-01', 'pokok' => '459400.00',
                'bunga' => '40600.00', 'denda' => '0.00',
            ]),
        ]));
        $paid = $calculator->hitungUntukPinjaman($loan);
        $this->assertSame('2026-07-31', $paid['tanggal_acuan']);
        $this->assertSame(10, $paid['bulan_tunggakan']);
        $this->assertSame('macet', $paid['kolektibilitas']);
        $this->assertSame('13672200.00', $paid['total_sisa']);
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
