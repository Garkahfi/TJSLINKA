<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkMonitoringReport;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Models\User;
use App\Services\Monitoring\PumkInternalMonitoringService;
use App\Services\Monitoring\PumkMonitoringCaptureService;
use App\Services\Pumk\PumkLoanSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PumkInternalMonitoringYearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_december_and_january_payments_have_distinct_positions_without_mutating_latest_cache(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-03-05 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2026-11-01', 10_000_000, 0);
        $this->payment($loan, '2026-12-01', 1_000_000);
        $this->payment($loan, '2027-01-01', 2_000_000);
        $cacheBefore = $loan->fresh()->total_sisa;
        $this->assertSame(['2026-12-01', '2027-01-01'], $loan->fresh()->angsuran->pluck('periode')->map(fn ($date) => $date->toDateString())->all());

        $service = app(PumkInternalMonitoringService::class);
        $past = $service->report(2026);
        $current = $service->report(2027);

        $this->assertSame('2026-12-31', $past['as_of_date']);
        $this->assertSame(9000000.0, $past['saldo_pokok']);
        $this->assertSame(9000000.0, $past['total_saldo_piutang']);
        $this->assertSame(1, $past['payment_count']);
        $this->assertSame('2027-01-31', $current['as_of_date']);
        $this->assertSame(7000000.0, $current['saldo_pokok']);
        $this->assertSame(1, $current['payment_count']);
        $this->assertSame($cacheBefore, $loan->fresh()->total_sisa);
        $this->assertDatabaseCount('pumk_angsuran', 2);
        $this->assertDatabaseCount('pumk_monitoring_reports', 0);
    }

    public function test_new_year_without_payment_carries_last_known_balance_not_future_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-10 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2026-11-01', 10_000_000, 0);
        $loan->update(['selesai_angsuran' => '2029-12-01']);
        $this->payment($loan, '2026-12-01', 1_000_000);

        $data = app(PumkInternalMonitoringService::class)->report();

        $this->assertSame(2027, $data['year']);
        $this->assertSame('carryover', $data['status']);
        $this->assertTrue($data['carried_from_previous_year']);
        $this->assertSame('2026-12-31', $data['as_of_date']);
        $this->assertSame(9000000.0, $data['total_saldo_piutang']);
        $this->assertSame(0, $data['payment_count']);
        $this->assertNotContains(2029, $data['years']);
        $this->expectException(\InvalidArgumentException::class);
        app(PumkInternalMonitoringService::class)->report(2029);
    }

    public function test_opening_payment_aggregate_is_applied_once_and_future_payment_not_used(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2025-01-01', 10_000_000, 2_000_000);
        PumkSaldoAwal::create([
            'pinjaman_id' => $loan->id, 'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 2_000_000, 'bunga_masuk' => 500_000,
        ]);
        $this->payment($loan, '2026-01-01', 1_000_000, 100_000);
        $this->payment($loan, '2026-08-01', 500_000, 50_000);

        $data = app(PumkInternalMonitoringService::class)->report(2026);

        $this->assertSame('2026-08-31', $data['as_of_date']);
        $this->assertSame(6500000.0, $data['saldo_pokok']);
        $this->assertSame(1350000.0, $data['saldo_bunga']);
        $this->assertSame(7850000.0, $data['total_saldo_piutang']);
    }

    public function test_capture_is_idempotent_and_a_late_correction_creates_revision(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-10 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2026-11-01', 10_000_000, 0);
        $this->payment($loan, '2026-12-01', 1_000_000);
        $capture = app(PumkMonitoringCaptureService::class);
        $asOf = CarbonImmutable::parse('2026-12-31', 'Asia/Jakarta');

        $first = $capture->capture($asOf);
        $same = $capture->capture($asOf);
        $this->payment($loan, '2026-11-01', 500_000);
        $this->assertTrue(PumkMonitoringReport::firstOrFail()->needs_reconcile);
        $revised = $capture->capture($asOf);

        $this->assertSame('created', $first['status']);
        $this->assertSame('unchanged', $same['status']);
        $this->assertSame('revised', $revised['status']);
        $this->assertSame(2, $revised['revision']);
        $this->assertFalse(PumkMonitoringReport::firstOrFail()->needs_reconcile);
        $this->assertDatabaseCount('pumk_monitoring_reports', 1);
        $this->assertDatabaseCount('pumk_monitoring_positions', 1);
        $this->assertSame('8500000.00', PumkMonitoringReport::firstOrFail()->positions->firstOrFail()->saldo_pokok);
    }

    public function test_quality_distribution_uses_nominal_balance_while_binaan_counts_unique_people(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        foreach ([['Lancar', 9_000_000], ['Macet', 1_000_000]] as [$quality, $amount]) {
            $mitra = PumkMitra::create([
                'nama_mitra' => 'Mitra '.$quality,
                'source_key' => hash('sha256', 'nominal-'.$quality),
                'sektor_sumber' => 'Perdagangan',
                'wilayah_sumber' => 'Kota Madiun',
            ]);
            PumkPinjaman::create([
                'mitra_id' => $mitra->id,
                'source_key' => hash('sha256', 'nominal-loan-'.$quality),
                'tanggal_pencairan' => '2026-09-15',
                'pinjaman_pokok' => $amount,
                'pinjaman_bunga' => 0,
                'kolektibilitas' => $quality,
                'is_active' => true,
            ]);
        }

        $data = app(PumkInternalMonitoringService::class)->report(2026);
        $quality = collect($data['kolektibilitas'])->pluck('nilai', 'label');

        $this->assertSame(10_000_000.0, $data['total_saldo_piutang']);
        $this->assertSame(2, $data['total_binaan']);
        $this->assertSame(9_000_000.0, $quality['Lancar']);
        $this->assertSame(1_000_000.0, $quality['Macet']);
        $this->assertSame($data['total_saldo_piutang'], collect($data['sektor'])->sum('nilai'));
        $this->assertSame($data['total_saldo_piutang'], collect($data['sebaran_provinsi'])->sum('nilai'));
    }

    public function test_missing_balance_is_not_disguised_as_zero_but_known_zero_is_valid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Tanpa Bunga', 'source_key' => hash('sha256', 'missing-balance')]);
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'missing-balance-loan'),
            'tanggal_pencairan' => '2026-09-15',
            'pinjaman_pokok' => 100_000,
            'pinjaman_bunga' => null,
        ]);
        $service = app(PumkInternalMonitoringService::class);

        $missing = $service->report(2026);
        $this->assertSame('partial', $missing['status']);
        $this->assertSame(1, $missing['unknown_loans']);
        $this->assertNull($missing['total_saldo_piutang']);

        $loan->update(['pinjaman_pokok' => 0, 'pinjaman_bunga' => 0]);
        $zero = $service->report(2026);
        $this->assertSame(0.0, $zero['total_saldo_piutang']);
        $this->assertSame(0, $zero['total_binaan']);
    }

    public function test_selected_unavailable_year_and_invalid_parameter_do_not_fall_back_to_another_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        $this->loan('2026-01-01', 1_000_000, 0);
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);

        $this->actingAs($user, 'web')->get(route('monitoring.inka', ['year' => 2025]))
            ->assertOk()->assertSee('Data untuk periode ini belum tersedia');
        $this->get(route('monitoring.inka', ['year' => 2200]))
            ->assertSessionHasErrors('year');
    }

    public function test_new_capture_uses_new_classification_without_rewriting_prior_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Berubah',
            'source_key' => hash('sha256', 'mitra-berubah'),
            'sektor_sumber' => 'Perdagangan',
            'wilayah_sumber' => 'Kota Madiun',
        ]);
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'loan-berubah'),
            'tanggal_pencairan' => '2026-01-01',
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 0,
            'kolektibilitas' => 'Lancar',
        ]);
        $capture = app(PumkMonitoringCaptureService::class);
        $capture->capture(CarbonImmutable::parse('2026-12-31', 'Asia/Jakarta'));

        Carbon::setTestNow(Carbon::parse('2027-01-31 12:00:00', 'Asia/Jakarta'));
        $mitra->update(['sektor_sumber' => 'Industri']);
        $loan->update(['kolektibilitas' => 'Macet']);
        $capture->capture(CarbonImmutable::parse('2027-01-31', 'Asia/Jakarta'));
        $service = app(PumkInternalMonitoringService::class);

        $this->assertSame('Perdagangan', $service->report(2026)['sektor']->first()['label']);
        $this->assertSame('Lancar', $service->report(2026)['kolektibilitas']->first()['label']);
        $this->assertSame('Industri', $service->report(2027)['sektor']->first()['label']);
        $this->assertSame('Macet', $service->report(2027)['kolektibilitas']->first()['label']);
        $this->assertDatabaseCount('pumk_monitoring_reports', 2);
    }

    public function test_monthly_capture_on_january_first_closes_previous_december(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 01:15:00', 'Asia/Jakarta'));
        $this->loan('2026-11-01', 1_000_000, 0);

        $this->artisan('pumk:monitoring-capture')->assertSuccessful();

        $this->assertSame('2026-12-31', PumkMonitoringReport::firstOrFail()->as_of_date->toDateString());
        $this->assertDatabaseCount('pumk_monitoring_positions', 1);
    }

    public function test_two_loans_for_one_mitra_count_as_one_binaan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Dua Fasilitas', 'source_key' => hash('sha256', 'mitra-dua-fasilitas')]);
        foreach ([1_000_000, 2_000_000] as $amount) {
            PumkPinjaman::create([
                'mitra_id' => $mitra->id,
                'source_key' => hash('sha256', 'loan-dua-fasilitas-'.$amount),
                'tanggal_pencairan' => '2026-09-15',
                'pinjaman_pokok' => $amount,
                'pinjaman_bunga' => 0,
            ]);
        }

        $data = app(PumkInternalMonitoringService::class)->report(2026);

        $this->assertSame(3_000_000.0, $data['total_saldo_piutang']);
        $this->assertSame(1, $data['total_binaan']);
        $this->assertSame(2, $data['known_loans']);
    }

    public function test_imported_source_cutoff_is_position_evidence_even_without_disbursement_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Tanggal Kosong', 'source_key' => hash('sha256', 'source-no-date')]);
        PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'source-loan-no-date'),
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => ['sisa_pokok' => '1000000.00', 'sisa_bunga' => '0.00'],
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 0,
        ]);

        $data = app(PumkInternalMonitoringService::class)->report(2026);

        $this->assertSame('2026-07-31', $data['as_of_date']);
        $this->assertSame(1_000_000.0, $data['total_saldo_piutang']);
        $this->assertSame(1, $data['known_loans']);
    }

    public function test_imported_baseline_only_subtracts_manual_payments_after_its_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-10 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Baseline', 'source_key' => hash('sha256', 'baseline-mitra')]);
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'baseline-loan'),
            'tanggal_pencairan' => '2026-01-01',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '10000000.00', 'sisa_bunga' => '0.00',
                'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00',
                'total_denda_masuk' => '0.00', 'bulan_tunggakan' => null,
            ],
            'pinjaman_pokok' => 12_000_000,
            'pinjaman_bunga' => 0,
        ]);
        $this->payment($loan, '2026-08-01', 1_000_000);
        $this->payment($loan, '2027-01-01', 2_000_000);
        $service = app(PumkInternalMonitoringService::class);

        $past = $service->report(2026);
        $current = $service->report(2027);

        $this->assertSame('2026-08-31', $past['as_of_date']);
        $this->assertSame(9_000_000.0, $past['total_saldo_piutang']);
        $this->assertSame(7_000_000.0, $current['total_saldo_piutang']);
        $this->assertSame(1, $past['payment_count']);
        $this->assertSame(1, $current['payment_count']);
    }

    public function test_imported_monitoring_uses_running_card_category_and_net_payment_delta(): void
    {
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Rumus', 'source_key' => hash('sha256', 'rumus-mitra'),
        ]);
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'rumus-loan'),
            'tanggal_pencairan' => '2026-07-01', 'source_updated_at' => '2026-07-31 23:59:59',
            'pinjaman_pokok' => '1200000.00', 'pinjaman_bunga' => '0.00',
            'mulai_angsuran' => '2026-07-01', 'nilai_angsuran_bulanan' => '100000.00',
            'kolektibilitas' => 'lancar', 'is_active' => true,
            'baseline_sumber' => [
                'sisa_pokok' => '1200000.00', 'sisa_bunga' => '0.00',
                'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00',
                'formula_sumber' => [
                    'mulai_angsuran' => '2026-07-01', 'angsuran_bulanan' => '100000.00',
                    'total_kewajiban' => '1200000.00', 'tanggal_acuan' => '2026-07-31',
                ],
            ],
        ]);
        $service = app(PumkInternalMonitoringService::class);
        $july = $service->positionForCapture(CarbonImmutable::parse('2026-07-31'))['rows'][0];
        $august = $service->positionForCapture(CarbonImmutable::parse('2026-08-31'))['rows'][0];
        $this->assertSame('Lancar', $july['kolektibilitas']);
        $this->assertSame('Kurang Lancar', $august['kolektibilitas']);
        $this->assertSame('1200000.00', $august['total']);

        $this->payment($loan, '2026-08-01', 100_000);
        $paid = $service->positionForCapture(CarbonImmutable::parse('2026-08-31'))['rows'][0];
        $this->assertSame('Lancar', $paid['kolektibilitas']);
        $this->assertSame('1100000.00', $paid['total']);
        $this->assertSame('calculated', $paid['classification_sources']['kolektibilitas']);

        // Workbook revisi kini telah mencakup pembayaran yang sama; jangan
        // menguranginya lagi hanya karena transaksi manual masih ada.
        $baseline = $loan->fresh()->baseline_sumber;
        $baseline['sisa_pokok'] = '1100000.00';
        $baseline['total_pokok_masuk'] = '100000.00';
        $loan->forceFill(['baseline_sumber' => $baseline])->saveQuietly();
        $afterReimport = $service->positionForCapture(CarbonImmutable::parse('2026-08-31'))['rows'][0];
        $this->assertSame('1100000.00', $afterReimport['total']);
        $this->assertSame('Lancar', $afterReimport['kolektibilitas']);
    }

    public function test_months_without_evidence_remain_null_in_trend_and_reload_does_not_advance_source_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-10 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2026-11-01', 10_000_000, 0);
        $this->payment($loan, '2026-12-01', 1_000_000);
        $service = app(PumkInternalMonitoringService::class);

        $first = $service->report(2026);
        Carbon::setTestNow(Carbon::parse('2027-02-11 12:00:00', 'Asia/Jakarta'));
        $reloaded = $service->report(2026);

        $this->assertSame($first['as_of_date'], $reloaded['as_of_date']);
        $this->assertSame($first['updated_at'], $reloaded['updated_at']);
        $this->assertNull($first['tren_kolektibilitas']['datasets'][0]['data'][0]);
        $this->assertSame(9_000_000.0, $first['tren_kolektibilitas']['datasets'][0]['data'][11]);
    }

    public function test_monitoring_year_is_url_scoped_and_guest_cannot_open_the_dashboards(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-10 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2026-11-01', 10_000_000, 0);
        $this->payment($loan, '2026-12-01', 1_000_000);
        $this->payment($loan, '2027-01-01', 2_000_000);
        foreach (['monitoring.tjsl', 'monitoring.bri', 'monitoring.inka'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $first = $this->actingAs($user, 'web')->get(route('monitoring.inka', ['year' => 2026]));
        $second = $this->get(route('monitoring.inka', ['year' => 2027]));
        $again = $this->get(route('monitoring.inka', ['year' => 2026]));

        $first->assertOk();
        $second->assertOk();
        $again->assertOk();
        $this->assertSame(2026, $first->viewData('pumkLiveDashboard')['year']);
        $this->assertSame(2027, $second->viewData('pumkLiveDashboard')['year']);
        $this->assertSame(2026, $again->viewData('pumkLiveDashboard')['year']);
        $this->assertSame(9_000_000.0, $first->viewData('pumkLiveDashboard')['total_saldo_piutang']);
        $this->assertSame(7_000_000.0, $second->viewData('pumkLiveDashboard')['total_saldo_piutang']);
    }

    public function test_empty_portfolio_does_not_invent_a_year_balance_or_update_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-10 12:00:00', 'Asia/Jakarta'));

        $data = app(PumkInternalMonitoringService::class)->report();

        $this->assertSame('unavailable', $data['status']);
        $this->assertSame(2027, $data['year']);
        $this->assertSame([2027, 2026, 2025], $data['years']);
        $this->assertNull($data['as_of_date']);
        $this->assertNull($data['updated_at']);
        $this->assertNull($data['total_saldo_piutang']);
    }

    public function test_loan_marked_paid_later_still_appears_in_prior_year_position(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-03-05 12:00:00', 'Asia/Jakarta'));
        $loan = $this->loan('2026-11-01', 10_000_000, 0);
        $this->payment($loan, '2026-12-01', 1_000_000);
        $this->payment($loan, '2027-01-01', 9_000_000);
        $loan->update(['is_active' => false, 'lunas_at' => '2027-02-01 12:00:00']);

        $past = app(PumkInternalMonitoringService::class)->report(2026);

        $this->assertSame(9_000_000.0, $past['total_saldo_piutang']);
        $this->assertSame(1, $past['total_binaan']);
        $this->assertSame(1, $past['known_loans']);
    }

    public function test_missing_classifications_keep_their_nominal_in_explicit_groups(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
        $this->loan('2026-01-01', 100_000, 0);

        $data = app(PumkInternalMonitoringService::class)->report(2026);

        $this->assertSame(100_000.0, $data['total_saldo_piutang']);
        $this->assertTrue($data['classification_limited']);
        $this->assertSame('Belum Terverifikasi', $data['sektor']->first()['label']);
        $this->assertSame('Belum Terverifikasi', $data['sebaran_provinsi']->first()['label']);
        $this->assertSame('Belum Dinilai', $data['kolektibilitas']->first()['label']);
        $this->assertSame(100_000.0, $data['sektor']->sum('nilai'));
        $this->assertSame(100_000.0, $data['sebaran_provinsi']->sum('nilai'));
        $this->assertSame(100_000.0, $data['kolektibilitas']->sum('nilai'));
    }

    public function test_tolerance_closure_removes_loan_only_from_its_effective_day_and_can_capture_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 18:00:00', 'Asia/Jakarta'));
        config()->set('pumk.settlement_tolerance', '10000.00');
        $loan = $this->loan('2026-01-01', 5_000, 0);
        $user = User::factory()->create(['role' => 'pumk_admin', 'is_active' => true, 'must_change_password' => false]);
        app(PumkLoanSettlementService::class)->settle($loan->mitra_id, $loan->id, 'Selisih diperiksa.', $user->id);

        $service = app(PumkInternalMonitoringService::class);
        $loans = PumkPinjaman::with(['mitra', 'saldoAwal', 'angsuran'])->get();
        $reports = PumkMonitoringReport::with('positions')->get();
        $before = $service->positionsAt($loans, CarbonImmutable::parse('2026-09-27', 'Asia/Jakarta'), $reports);
        $after = $service->positionsAt($loans, CarbonImmutable::parse('2026-09-28', 'Asia/Jakarta'), $reports);
        $dashboard = $service->report(2026);

        $this->assertCount(1, $before['rows']);
        $this->assertSame('5000.00', $before['rows'][0]['total']);
        $this->assertSame([], $after['rows']);
        $this->assertSame(1, $after['closed']);
        $this->assertSame('available', $dashboard['status']);
        $this->assertSame('2026-09-28', $dashboard['as_of_date']);
        $this->assertSame(0.0, $dashboard['total_saldo_piutang']);
        $this->assertSame(0, $dashboard['total_binaan']);
        $this->assertSame('created', app(PumkMonitoringCaptureService::class)
            ->capture(CarbonImmutable::parse('2026-09-28', 'Asia/Jakarta'))['status']);
        $this->assertDatabaseCount('pumk_monitoring_positions', 0);
    }

    public function test_open_negative_balance_does_not_reduce_positive_portfolio_or_enter_charts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Jakarta'));
        $negative = $this->loan('2026-01-01', 100_000, 0);
        $this->payment($negative, '2026-09-01', 105_000);
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Positif', 'source_key' => hash('sha256', 'positive-mitra')]);
        PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'positive-loan'),
            'tanggal_pencairan' => '2026-01-01', 'pinjaman_pokok' => 1_000_000, 'pinjaman_bunga' => 0,
            'kolektibilitas' => 'Lancar',
        ]);

        $data = app(PumkInternalMonitoringService::class)->report(2026);

        $this->assertSame(1_000_000.0, $data['total_saldo_piutang']);
        $this->assertSame(-5_000.0, $data['negative_total']);
        $this->assertSame(995_000.0, $data['net_known_balance']);
        $this->assertSame(1, $data['negative_loans']);
        $this->assertSame(1, $data['total_binaan']);
        $this->assertSame(1_000_000.0, $data['sektor']->sum('nilai'));
        $this->assertSame(1_000_000.0, $data['kolektibilitas']->sum('nilai'));
    }

    public function test_only_selected_dashboard_is_rendered_and_old_bri_year_link_redirects(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($user, 'web')->get(route('monitoring.tjsl'))
            ->assertOk()->assertSee('id="per-pilar-chart"', false)
            ->assertSee('leaflet@1.9.4/dist/leaflet.js')
            ->assertDontSee('id="pumk-live-sector-chart"', false)
            ->assertDontSee('id="pumk-portfolio-chart"', false);
        $this->get(route('monitoring.bri'))
            ->assertOk()->assertSee('id="pumk-portfolio-chart"', false)
            ->assertSee('leaflet@1.9.4/dist/leaflet.js')
            ->assertDontSee('id="per-pilar-chart"', false)
            ->assertDontSee('id="pumk-live-sector-chart"', false);
        $this->get(route('monitoring.inka'))
            ->assertOk()->assertSee('id="pumk-live-sector-chart"', false)
            ->assertDontSee('leaflet@1.9.4/dist/leaflet.js')
            ->assertDontSee('id="per-pilar-chart"', false)
            ->assertDontSee('id="pumk-portfolio-chart"', false);
        $this->get(route('home', ['pumk_year' => 2026]))
            ->assertRedirect(route('monitoring.bri', ['pumk_year' => 2026]));
    }

    private function loan(string $disbursed, int $principal, int $interest): PumkPinjaman
    {
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Uji', 'source_key' => hash('sha256', 'monitoring-mitra')]);

        return PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'monitoring-loan'),
            'tanggal_pencairan' => $disbursed, 'pinjaman_pokok' => $principal,
            'pinjaman_bunga' => $interest, 'status' => 'aktif', 'is_active' => true,
        ]);
    }

    private function payment(PumkPinjaman $loan, string $period, int $principal, int $interest = 0): void
    {
        PumkAngsuran::create([
            'pinjaman_id' => $loan->id, 'periode' => $period,
            'pokok' => $principal, 'bunga' => $interest,
        ]);
    }
}
