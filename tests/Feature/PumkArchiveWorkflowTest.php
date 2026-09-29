<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use App\Services\Monitoring\PumkInternalMonitoringService;
use App\Services\Monitoring\PumkMonitoringCaptureService;
use App\Services\Pumk\PiutangCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PumkArchiveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Jakarta'));
        config()->set('pumk.settlement_tolerance', '100000.00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_large_overpayment_can_close_and_retains_its_signed_balance(): void
    {
        [$user, $mitra, $loan] = $this->fixture(100000);
        PumkAngsuran::create(['pinjaman_id' => $loan->id, 'periode' => '2026-09-01', 'pokok' => 2243279, 'bunga' => 0, 'created_by' => $user->id]);
        $url = route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $loan]);
        $this->actingAs($user, 'pumk')->get(route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $loan->id]))
            ->assertOk()->assertSee('Pinjaman ini dapat ditandai lunas.')->assertSee('konfirmasi_kelebihan_bayar');
        $this->post($url, ['lunas_note' => 'Kelebihan bayar sudah diperiksa.'])->assertSessionHasErrors('konfirmasi_kelebihan_bayar');
        $this->post($url, ['lunas_note' => 'Kelebihan bayar sudah diperiksa.', 'konfirmasi_kelebihan_bayar' => 1])->assertSessionHasNoErrors();
        $this->assertSame('-2143279.00', $loan->fresh()->lunas_total_saldo);
        $this->assertSame('kelebihan_bayar', $loan->fresh()->lunas_reason);
        $this->assertSame(1, $loan->closures()->count());
        $this->assertDatabaseCount('pumk_angsuran', 1);
        $report = app(PumkInternalMonitoringService::class)->report(2026);
        $this->assertSame(1, $report['closed_loans']);
        $this->assertSame(0, $report['negative_loans']);
    }

    public function test_one_hundred_thousand_is_inclusive_and_larger_balance_is_rejected(): void
    {
        [$user, $mitra, $loan] = $this->fixture(100000);
        $url = route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $loan]);
        $this->actingAs($user, 'pumk')->post($url)->assertSessionHasErrors('lunas_note');
        $this->post($url, ['lunas_note' => 'Penyelesaian selisih sesuai batas.'])->assertSessionHasNoErrors();
        $this->assertSame('100000.00', $loan->fresh()->lunas_total_saldo);
        $this->assertSame('100000.00', $loan->fresh()->lunas_tolerance_applied);
        [$other, $otherMitra, $above] = $this->fixture(100000.01);
        $this->actingAs($other, 'pumk')->post(route('pumk-admin.mitra.pinjaman.lunas', [$otherMitra, $above]), ['lunas_note' => 'Mencoba batas.'])
            ->assertSessionHasErrors('lunas');
        $this->assertSame('aktif', $above->fresh()->status);
    }

    public function test_imported_overpayment_reopens_with_its_original_baseline(): void
    {
        [$user, $mitra, $loan] = $this->fixture(1000000);
        $baseline = [
            'sisa_pokok' => '-2143279.00', 'sisa_bunga' => '0.00',
            'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00', 'total_denda_masuk' => '0.00',
            'bulan_tunggakan' => 0, 'nilai_tunggakan' => '0.00', 'kolektibilitas' => 'lancar',
        ];
        $loan->update(['source_updated_at' => '2026-09-01 00:00:00', 'baseline_sumber' => $baseline]);
        app(PiutangCalculator::class)->sinkronkanCache($loan);
        $this->actingAs($user, 'pumk')->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $loan]), [
            'lunas_note' => 'Baseline kelebihan bayar telah diperiksa.', 'konfirmasi_kelebihan_bayar' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('lunas', $loan->fresh()->status);
        $this->post(route('pumk-admin.mitra.pinjaman.reopen', [$mitra, $loan]), ['reopen_note' => 'Periksa kembali dokumen sumber.'])
            ->assertSessionHasNoErrors();
        $this->assertEquals($baseline, $loan->fresh()->baseline_sumber);
        $this->assertSame('-2143279.00', $loan->fresh()->total_sisa);
        $this->assertSame('-2143279.00', app(PiutangCalculator::class)->hitungUntukPinjaman($loan->fresh())['total_sisa']);
        $this->assertSame(1, $mitra->pinjaman()->count());
        $this->assertDatabaseCount('pumk_angsuran', 0);
    }

    public function test_archived_spj_upload_keeps_same_loan_balance_status_and_payments(): void
    {
        Storage::fake('local');
        [$user, $mitra, $loan] = $this->fixture(75000);
        $this->close($user, $mitra, $loan);
        $before = $loan->fresh()->only(['status', 'is_active', 'pinjaman_pokok', 'sisa_pokok', 'baseline_sumber', 'lunas_at', 'lunas_total_saldo']);
        $this->get(route('pumk-admin.mitra.edit', [$mitra, 'pinjaman' => $loan->id]))->assertOk()->assertSee('Edit Arsip dan Dokumen');
        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => $mitra->nama_mitra, 'pinjaman_id' => $loan->id,
            'edit_reason' => 'Melengkapi dokumen SPJ lama.', 'spj_awal' => 'SPJ/ARSIP/001',
            'dokumen_spj_awal' => UploadedFile::fake()->create('spj-lama.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertEquals($before, $loan->fresh()->only(array_keys($before)));
        $this->assertFalse($mitra->fresh()->is_active);
        $this->assertSame(1, $mitra->pinjaman()->count());
        $this->assertDatabaseHas('pumk_pinjaman_dokumen', ['pinjaman_id' => $loan->id, 'nama_file_asli' => 'spj-lama.pdf']);
        $this->assertDatabaseCount('pumk_angsuran', 0);
    }

    public function test_archive_financial_edits_are_rejected_and_blank_fields_cannot_clear_amounts(): void
    {
        [$user, $mitra, $loan] = $this->fixture(75000);
        $this->close($user, $mitra, $loan);
        $data = ['nama_mitra' => $mitra->nama_mitra, 'pinjaman_id' => $loan->id, 'edit_reason' => 'Koreksi administrasi.'];
        $this->put(route('pumk-admin.mitra.update', $mitra), $data + ['pinjaman_pokok' => 0])->assertSessionHasErrors('pinjaman_pokok');
        $this->put(route('pumk-admin.mitra.update', $mitra), $data + ['pinjaman_pokok' => null])->assertSessionHasNoErrors();
        $this->assertEquals(75000, $loan->fresh()->pinjaman_pokok);
        $this->assertSame(1, $mitra->pinjaman()->count());
    }

    public function test_reopen_uses_existing_loan_and_preserves_closed_period_in_monitoring(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 10:00:00', 'Asia/Jakarta'));
        [$user, $mitra, $loan] = $this->fixture(75000);
        $this->close($user, $mitra, $loan);
        $capture = app(PumkMonitoringCaptureService::class);
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00', 'Asia/Jakarta'));
        $capture->capture(CarbonImmutable::parse('2026-08-31', 'Asia/Jakarta'));
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Jakarta'));
        $url = route('pumk-admin.mitra.pinjaman.reopen', [$mitra, $loan]);
        $this->post($url)->assertSessionHasErrors('reopen_note');
        $this->post($url, ['reopen_note' => 'Koreksi keputusan penutupan.'])->assertSessionHasNoErrors();
        $this->assertSame(1, $mitra->pinjaman()->count());
        $this->assertSame('aktif', $loan->fresh()->status);
        $this->assertSame('75000.00', $loan->fresh()->sisa_pokok);
        $this->assertTrue($mitra->fresh()->is_active);
        $this->assertTrue($loan->fresh()->isClosedAt(CarbonImmutable::parse('2026-08-31', 'Asia/Jakarta')));
        $this->assertFalse($loan->fresh()->isClosedAt(CarbonImmutable::parse('2026-09-29', 'Asia/Jakarta')));
        $positions = app(PumkInternalMonitoringService::class)->positionForCapture(CarbonImmutable::parse('2026-08-31', 'Asia/Jakarta'));
        $this->assertSame(1, $positions['closed']);
        $this->assertSame([], $positions['rows']);
        $this->assertSame(75000.0, app(PumkInternalMonitoringService::class)->report(2026)['total_saldo_piutang']);
        $this->close($user, $mitra, $loan);
        $this->assertSame(2, $loan->closures()->count());
        $this->assertSame(1, app(PumkInternalMonitoringService::class)->report(2026)['closed_loans']);
    }

    public function test_a_new_facility_requires_explicit_intent_and_complete_positive_principal(): void
    {
        [$user, $mitra, $loan] = $this->fixture(75000);
        $this->close($user, $mitra, $loan);
        $this->put(route('pumk-admin.mitra.update', $mitra), ['nama_mitra' => $mitra->nama_mitra, 'edit_reason' => 'Perbaiki identitas.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $mitra->pinjaman()->count());
        $this->put(route('pumk-admin.mitra.update', $mitra), ['nama_mitra' => $mitra->nama_mitra, 'new_loan' => 1])
            ->assertSessionHasErrors(['pinjaman_pokok', 'pinjaman_bunga', 'tanggal_pencairan']);
        $this->assertSame(1, $mitra->pinjaman()->count());
    }

    public function test_archive_edit_and_reopen_reject_a_loan_owned_by_another_partner(): void
    {
        [$user, $mitra, $loan] = $this->fixture(75000);
        [, $otherMitra, $otherLoan] = $this->fixture(10000);
        $this->close($user, $mitra, $loan);
        $this->get(route('pumk-admin.mitra.edit', [$mitra, 'pinjaman' => $otherLoan->id]))->assertNotFound();
        $this->put(route('pumk-admin.mitra.update', $mitra), ['nama_mitra' => $mitra->nama_mitra, 'pinjaman_id' => $otherLoan->id, 'edit_reason' => 'Mencoba pinjaman lain.'])->assertNotFound();
        $this->post(route('pumk-admin.mitra.pinjaman.reopen', [$otherMitra, $loan]), ['reopen_note' => 'Mencoba pinjaman lain.'])->assertNotFound();
    }

    public function test_installment_changes_record_new_collectibility_for_monitoring(): void
    {
        [$user, $mitra, $loan] = $this->fixture(1200000);
        $loan->update(['mulai_angsuran' => '2026-01-01', 'selesai_angsuran' => '2026-12-01', 'nilai_angsuran_bulanan' => 100000]);
        app(PiutangCalculator::class)->sinkronkanCache($loan);
        app(\App\Services\Monitoring\PumkClassificationService::class)->record(null, $loan, 'kolektibilitas', 'diragukan', CarbonImmutable::parse('2026-09-01', 'Asia/Jakarta'), 'estimate', 'test-old');
        $this->actingAs($user, 'pumk')->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $loan]), [
            'periode' => '2026-09', 'pokok' => 900000, 'bunga' => 0, 'denda' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertSame('lancar', $loan->fresh()->kolektibilitas);
        $this->assertSame('lancar', app(PumkInternalMonitoringService::class)->report(2026)['kolektibilitas']->first()['label']);
    }

    public function test_audit_uses_the_recorded_tolerance_and_does_not_change_data(): void
    {
        [$user, $mitra, $loan] = $this->fixture(75000);
        $this->close($user, $mitra, $loan);
        $before = $loan->fresh()->getAttributes();
        config()->set('pumk.settlement_tolerance', '10000.00');
        $this->artisan('pumk:audit-settlements', ['--mitra' => $mitra->id, '--json' => true])->assertSuccessful();
        $this->assertSame($before, $loan->fresh()->getAttributes());
        $this->assertSame(1, $loan->closures()->count());
        $this->assertDatabaseCount('pumk_monitoring_reports', 0);
    }

    public function test_audit_flags_an_empty_facility_and_legacy_missing_metadata(): void
    {
        [$user, $mitra, $loan] = $this->fixture(75000);
        $loan->update(['status' => 'lunas', 'is_active' => false, 'lunas_at' => null]);
        $this->artisan('pumk:audit-settlements', ['--mitra' => $mitra->id])->assertFailed();
        $loan->update(['status' => 'aktif', 'is_active' => true, 'pinjaman_pokok' => null, 'pinjaman_bunga' => null]);
        $this->artisan('pumk:audit-settlements', ['--mitra' => $mitra->id])->assertFailed();
        $this->assertNull($loan->fresh()->pinjaman_pokok);
        $this->assertSame(1, $mitra->pinjaman()->count());
    }

    public function test_void_unfunded_facility_is_kept_for_audit_but_excluded_from_monitoring(): void
    {
        [$user, $mitra, $funded] = $this->fixture(75000);
        $empty = $mitra->pinjaman()->create([
            'source_key' => hash('sha256', 'unfunded-void-fixture'),
            'pinjaman_pokok' => null, 'pinjaman_bunga' => null,
            'created_by' => $user->id, 'status' => 'aktif', 'is_active' => true,
        ]);
        $service = app(PumkInternalMonitoringService::class);
        $before = $service->positionForCapture(CarbonImmutable::now('Asia/Jakarta'));
        $this->assertSame(1, $before['unknown']);

        $empty->update(['status' => 'nonaktif', 'is_active' => false]);
        $after = $service->positionForCapture(CarbonImmutable::now('Asia/Jakarta'));
        $this->assertSame(0, $after['unknown']);
        $this->assertSame([$funded->id], array_column($after['rows'], 'pinjaman_id'));
        $this->assertDatabaseHas('pumk_pinjaman', ['id' => $empty->id, 'status' => 'nonaktif']);
        $this->artisan('pumk:audit-settlements', ['--mitra' => $mitra->id, '--json' => true])->assertSuccessful();
    }

    private function fixture(float $principal): array
    {
        $user = User::factory()->create(['role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Regresi', 'source_key' => hash('sha256', uniqid()), 'is_active' => true]);
        $loan = PumkPinjaman::create(['mitra_id' => $mitra->id, 'source_key' => hash('sha256', uniqid()), 'pinjaman_pokok' => $principal, 'pinjaman_bunga' => 0,
            'tanggal_pencairan' => '2026-01-01', 'status' => 'aktif', 'is_active' => true]);
        app(PiutangCalculator::class)->sinkronkanCache($loan);

        return [$user, $mitra, $loan];
    }

    private function close(User $user, PumkMitra $mitra, PumkPinjaman $loan): void
    {
        $this->actingAs($user, 'pumk')->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $loan]), ['lunas_note' => 'Penyelesaian selisih terverifikasi.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('lunas', $loan->fresh()->status);
    }
}
