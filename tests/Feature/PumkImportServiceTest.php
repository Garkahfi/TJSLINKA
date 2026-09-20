<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkImportBatch;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Models\User;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\KartuPiutangService;
use App\Services\Pumk\PumkImportRowException;
use App\Services\Pumk\PumkImportService;
use App\Services\Pumk\PumkScientificMoneyRepair;
use App\Services\Pumk\PumkXlsxReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PumkImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reimport_preserves_manual_contract_document_and_flags_changed_number(): void
    {
        Storage::fake('local');
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('kontrak-awal.xlsx'));
        $loan = PumkPinjaman::firstOrFail();
        $path = "pumk/pinjaman/{$loan->id}/kontrak/spj.pdf";
        Storage::disk('local')->put($path, 'dokumen uji');
        $document = PumkPinjamanDokumen::create([
            'pinjaman_id' => $loan->id, 'jenis_dokumen' => 'spj_awal',
            'file_path' => $path, 'nama_file_asli' => 'spj.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 11, 'uploaded_at' => now(),
        ]);

        $cells['C'] = 'SPJ-002';
        $result = $this->invokeImportRow($cells, $this->batch('kontrak-revisi.xlsx'));

        $this->assertContains('contract_number_changed_with_manual_document_spj_awal', $result['warnings']);
        $this->assertSame('SPJ-002', $loan->fresh()->spj_awal);
        $this->assertSame($path, $document->fresh()->file_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_reimport_preserves_manual_payment_proof_on_period_conflict(): void
    {
        Storage::fake('local');
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('angsuran-proof-awal.xlsx'));
        $loan = PumkPinjaman::firstOrFail();
        $loan->angsuran()->delete();
        $admin = User::factory()->create(['role' => 'pumk_admin']);
        $path = "pumk/angsuran/manual/bukti.pdf";
        Storage::disk('local')->put($path, 'proof');
        $manual = PumkAngsuran::create([
            'pinjaman_id' => $loan->id, 'periode' => '2026-01-01',
            'pokok' => 95000, 'bunga' => 5000, 'denda' => 0, 'created_by' => $admin->id,
            'bukti_pembayaran_path' => $path, 'bukti_pembayaran_nama_asli' => 'bukti.pdf',
            'bukti_pembayaran_mime' => 'application/pdf', 'bukti_pembayaran_size' => 5,
            'bukti_pembayaran_uploaded_at' => now(),
        ]);

        $result = $this->invokeImportRow($cells, $this->batch('angsuran-proof-reimport.xlsx'));

        $this->assertContains('manual_angsuran_conflict_1', $result['warnings']);
        $this->assertSame($path, $manual->fresh()->bukti_pembayaran_path);
        $this->assertSame('bukti.pdf', $manual->fresh()->bukti_pembayaran_nama_asli);
        $this->assertNull($manual->fresh()->batch_id);
        Storage::disk('local')->assertExists($path);
    }

    public function test_scientific_excel_numbers_keep_their_full_monetary_value_on_import_and_card(): void
    {
        $cells = $this->validCells();
        $cells['AD'] = '1.5E7';
        $cells['AF'] = '1274014';
        $cells['AG'] = '16274014';
        $cells['AX'] = '1.666459E6';
        $cells['AY'] = '319845';
        $cells['BA'] = '1986304';
        $cells['AU'] = '13333541';
        $cells['AV'] = '954169';
        $cells['AW'] = '14287710';

        $this->invokeImportRow($cells, $this->batch('scientific.xlsx'));

        $loan = PumkPinjaman::query()->firstOrFail()->fresh();
        $this->assertSame('15000000.00', $loan->pinjaman_pokok);
        $this->assertSame('16274014.00', $loan->total_pinjaman);
        $this->assertSame('1666459.00', $loan->saldoAwal->pokok_masuk);
        $this->assertSame('13333541.00', $loan->sisa_pokok);
        $this->assertSame('1766459.00', $loan->baseline_sumber['total_pokok_masuk']);

        $card = app(KartuPiutangService::class)->buat($loan);
        $this->assertSame('13333541.00', $card['jadwal'][0]['saldo_pokok']);
    }

    public function test_numeric_parser_rejects_malformed_exponents_instead_of_truncating_them(): void
    {
        $method = new ReflectionMethod(app(PumkImportService::class), 'decimal');
        $method->setAccessible(true);

        $this->assertSame('12000000', $method->invoke(app(PumkImportService::class), '1.2E7'));
        $this->assertSame('0.012', $method->invoke(app(PumkImportService::class), '1.2E-2'));
        $this->assertSame('-9929479', $method->invoke(app(PumkImportService::class), '-9.929479E6'));
        $this->assertNull($method->invoke(app(PumkImportService::class), '1.5E'));
    }

    public function test_scientific_repair_preserves_manual_payment_paid_status_and_source_baseline(): void
    {
        $cells = $this->validCells();
        $cells['AD'] = '1.5E7';
        $cells['AF'] = '1274014';
        $cells['AG'] = '16274014';
        $cells['AX'] = '1.666459E6';
        $cells['AU'] = '13333541';
        $cells['AV'] = '954169';
        $cells['AW'] = '14287710';
        $this->invokeImportRow($cells, $this->batch('sebelum-koreksi.xlsx'));

        $loan = PumkPinjaman::query()->firstOrFail();
        $loan->forceFill(['pinjaman_pokok' => '1.57'])->save();
        $loan->saldoAwal->forceFill(['pokok_masuk' => '1.66'])->save();
        $baseline = $loan->baseline_sumber;
        $baseline['total_pokok_masuk'] = bcsub($baseline['total_pokok_masuk'], '1666457.34', 2);
        $loan->forceFill(['baseline_sumber' => $baseline])->saveQuietly();

        $admin = User::factory()->create(['role' => 'pumk_admin']);
        $payment = PumkAngsuran::create([
            'pinjaman_id' => $loan->id,
            'periode' => '2026-08-01',
            'pokok' => '1000',
            'bunga' => '0',
            'denda' => '0',
            'created_by' => $admin->id,
        ]);
        $loan->fresh()->forceFill(['status' => PumkPinjaman::STATUS_LUNAS, 'is_active' => false])->save();

        $rows = [['worksheet_row' => 9, 'cells' => $cells]];
        $service = app(PumkScientificMoneyRepair::class);
        $this->assertSame(['rows' => 1, 'principal' => 1, 'opening_principal' => 1, 'conflicts' => []], $service->run($rows));
        $this->assertSame('1.57', $loan->fresh()->pinjaman_pokok);

        $service->run($rows, true);
        $loan->refresh();
        $this->assertSame('15000000.00', $loan->pinjaman_pokok);
        $this->assertSame('16274014.00', $loan->total_pinjaman);
        $this->assertSame('1666459.00', $loan->saldoAwal->fresh()->pokok_masuk);
        $this->assertSame('13333541.00', $loan->baseline_sumber['sisa_pokok']);
        $this->assertSame('13332541.00', $loan->sisa_pokok);
        $this->assertSame('2026-07-31', $loan->source_updated_at->toDateString());
        $this->assertSame(PumkPinjaman::STATUS_LUNAS, $loan->status);
        $this->assertFalse($loan->is_active);
        $this->assertSame($admin->id, $payment->fresh()->created_by);
        $this->assertNull($payment->fresh()->batch_id);
        $this->assertSame(0, $service->run($rows)['principal']);

        $mitra = $loan->mitra;
        $this->actingAs($admin, 'pumk')
            ->get(route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $loan->id]))
            ->assertOk()->assertSee('Rp 15.000.000');
        $excel = $this->get(route('pumk-admin.mitra.kartu.excel', [$mitra, $loan]));
        $excel->assertOk();
        $this->assertStringContainsString('<Data ss:Type="Number">15000000</Data>', $excel->getContent());
        $this->get(route('pumk-admin.mitra.kartu.pdf', [$mitra, $loan]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $super = User::factory()->create([
            'role' => 'super_admin', 'is_admin' => true,
            'is_active' => true, 'must_change_password' => false,
        ]);
        $this->actingAs($super, 'superadmin')
            ->get(route('superadmin.pumk.kartu', [$mitra, 'pinjaman' => $loan->id]))
            ->assertOk()->assertSee('15.000.000');
    }

    public function test_scientific_repair_refuses_a_value_that_was_not_produced_by_the_old_parser(): void
    {
        $cells = $this->validCells();
        $cells['AD'] = '1.2E7';
        $this->invokeImportRow($cells, $this->batch('konflik-koreksi.xlsx'));
        $loan = PumkPinjaman::query()->firstOrFail();
        $loan->pinjaman_pokok = '9999999';
        $loan->save();

        $rows = [['worksheet_row' => 9, 'cells' => $cells]];
        $result = app(PumkScientificMoneyRepair::class)->run($rows);
        $this->assertSame([1], $result['conflicts']);

        try {
            app(PumkScientificMoneyRepair::class)->run($rows, true);
            $this->fail('Konflik harus membatalkan koreksi.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Koreksi dibatalkan', $exception->getMessage());
        }
        $this->assertSame('9999999.00', $loan->fresh()->pinjaman_pokok);
    }

    public function test_blank_months_are_not_materialized_and_short_months_do_not_overflow(): void
    {
        $batch = $this->batch('uji-periode.xlsx');
        $cells = $this->validCells();

        foreach (['BB', 'BC', 'BD'] as $column) {
            unset($cells[$column]);
        }
        $cells['BE'] = '100000';
        $cells['BF'] = '10000';
        $cells['BG'] = '110000';
        $cells['BH'] = '200000';
        $cells['BI'] = '20000';
        $cells['BJ'] = '220000';
        $cells['AL'] = '550000';
        $cells['AM'] = '55000';
        $cells['AN'] = '605000';

        $this->invokeImportRow($cells, $batch);

        $angsuran = PumkAngsuran::query()->orderBy('periode')->get();

        $this->assertCount(2, $angsuran);
        $this->assertSame('2026-02-01', $angsuran[0]->periode->toDateString());
        $this->assertSame('2026-03-01', $angsuran[1]->periode->toDateString());
        $this->assertSame('110000.00', $angsuran[0]->total);
        $this->assertSame('220000.00', $angsuran[1]->total);
    }

    public function test_final_mapping_imports_reschedule_guarantee_and_financial_columns(): void
    {
        $cells = $this->validCells();
        $cells['D'] = 'SPJ-RS-001';
        $cells['E'] = 'SPJ-RS-002';
        $cells['F'] = null;
        $cells['G'] = 'SPJ-RS-004';
        $cells['P'] = 'BPKB Kendaraan';
        $cells['Q'] = 'AE 1234 XX';
        $cells['R'] = 'BPKB-9988';
        $cells['S'] = 'Honda';
        $cells['T'] = 'Vario';
        $cells['U'] = '2022';
        $cells['V'] = 'SHM 123';
        $cells['W'] = '380m2';
        $cells['X'] = 'Pemilik Contoh';
        $cells['Y'] = 'Alamat jaminan';

        $result = $this->invokeImportRow($cells, $this->batch('mapping-final.xlsx'));
        $pinjaman = PumkPinjaman::query()->firstOrFail();

        $this->assertSame('SPJ-RS-001', $pinjaman->reschedule_ke1);
        $this->assertSame('SPJ-RS-002', $pinjaman->reschedule_ke2);
        $this->assertNull($pinjaman->reschedule_ke3);
        $this->assertSame('SPJ-RS-004', $pinjaman->reschedule_ke4);
        $this->assertSame('BPKB Kendaraan', $pinjaman->jenis_jaminan);
        $this->assertSame('AE 1234 XX', $pinjaman->jaminan_no_pol);
        $this->assertSame('BPKB-9988', $pinjaman->jaminan_no_bpkb);
        $this->assertSame('2026-01-31', $pinjaman->tanggal_pencairan->toDateString());
        $this->assertSame(2026, $pinjaman->tahun_pencairan);
        $this->assertSame('1000000.00', $pinjaman->pinjaman_pokok);
        $this->assertSame('100000.00', $pinjaman->pinjaman_bunga);
        $this->assertSame('lancar', $pinjaman->kolektibilitas);
        $this->assertSame([], $result['comparison_differences']);
        $this->assertNotContains('missing_reschedule_ke2', $result['warnings']);
        $this->assertNotContains('missing_reschedule_ke3', $result['warnings']);
    }

    public function test_blank_source_values_do_not_erase_existing_manual_completions(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('baseline.xlsx'));

        $pinjaman = PumkPinjaman::query()->firstOrFail();
        $pinjaman->forceFill([
            'reschedule_ke2' => 'Dilengkapi manual',
            'jaminan_no_bpkb' => 'BPKB-MANUAL',
            'berkas_jaminan_path' => 'pumk/jaminan/manual.pdf',
        ])->save();
        $mitra = PumkMitra::query()->firstOrFail();
        $mitra->no_telepon_encrypted = '081234567890';
        $mitra->save();

        foreach (['E', 'R', 'AA', 'N'] as $column) {
            $cells[$column] = null;
        }

        $this->invokeImportRow($cells, $this->batch('reimport-baru.xlsx'));

        $this->assertDatabaseCount('pumk_mitra', 1);
        $this->assertDatabaseCount('pumk_pinjaman', 1);
        $this->assertSame('Dilengkapi manual', $pinjaman->fresh()->reschedule_ke2);
        $this->assertSame('BPKB-MANUAL', $pinjaman->fresh()->jaminan_no_bpkb);
        $this->assertSame('pumk/jaminan/manual.pdf', $pinjaman->fresh()->berkas_jaminan_path);
        $this->assertSame('081234567890', $mitra->fresh()->no_telepon_encrypted);
    }

    public function test_reimport_preserves_a_loan_that_was_manually_marked_paid(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('pinjaman-sebelum-lunas.xlsx'));

        $admin = User::factory()->create(['role' => 'pumk_admin']);
        $pinjaman = PumkPinjaman::query()->firstOrFail();
        $mitra = $pinjaman->mitra;
        $pinjaman->forceFill([
            'status' => PumkPinjaman::STATUS_LUNAS,
            'is_active' => false,
            'lunas_at' => now(),
            'lunas_by' => $admin->id,
            'lunas_note' => 'Ditandai manual oleh Admin PUMK',
        ])->save();
        $mitra->forceFill(['is_active' => false])->save();

        $result = $this->invokeImportRow($cells, $this->batch('pinjaman-setelah-lunas.xlsx'));

        $pinjaman->refresh();
        $this->assertSame(PumkPinjaman::STATUS_LUNAS, $pinjaman->status);
        $this->assertFalse($pinjaman->is_active);
        $this->assertSame($admin->id, $pinjaman->lunas_by);
        $this->assertSame('Ditandai manual oleh Admin PUMK', $pinjaman->lunas_note);
        $this->assertFalse($mitra->fresh()->is_active);
        $this->assertContains('manual_paid_status_conflict', $result['warnings']);
    }

    public function test_reimport_removes_stale_source_payment_when_month_becomes_blank(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('angsuran-awal.xlsx'));

        $this->assertDatabaseCount('pumk_angsuran', 1);

        foreach (['BB', 'BC', 'BD'] as $column) {
            $cells[$column] = null;
        }

        $this->invokeImportRow($cells, $this->batch('angsuran-dikosongkan.xlsx'));

        $this->assertDatabaseCount('pumk_angsuran', 0);
    }

    public function test_reimport_does_not_remove_manual_payment_when_source_month_is_blank(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('angsuran-sumber.xlsx'));

        $admin = User::factory()->create(['role' => 'pumk_admin']);
        $angsuran = PumkAngsuran::query()->firstOrFail();
        $angsuran->forceFill([
            'batch_id' => null,
            'created_by' => $admin->id,
        ])->save();

        foreach (['BB', 'BC', 'BD'] as $column) {
            $cells[$column] = null;
        }

        $this->invokeImportRow($cells, $this->batch('angsuran-sumber-kosong.xlsx'));

        $this->assertDatabaseCount('pumk_angsuran', 1);
        $this->assertSame($admin->id, $angsuran->fresh()->created_by);
        $this->assertNull($angsuran->fresh()->batch_id);
    }

    public function test_source_snapshot_uses_workbook_reference_date_even_when_only_child_data_changes(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow(
            $cells,
            $this->batch('snapshot-juli.xlsx'),
            CarbonImmutable::create(2026, 7, 31, 0, 0, 0, 'UTC'),
        );

        $cells['AX'] = '260000';
        $this->invokeImportRow(
            $cells,
            $this->batch('snapshot-agustus.xlsx'),
            CarbonImmutable::create(2026, 8, 31, 0, 0, 0, 'UTC'),
        );

        $pinjaman = PumkPinjaman::query()->firstOrFail();

        $this->assertSame('2026-08-31', $pinjaman->source_updated_at->toDateString());
        $this->assertSame('260000.00', $pinjaman->saldoAwal->pokok_masuk);
    }

    public function test_reimport_does_not_restore_opening_balance_after_admin_uses_detailed_history(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('histori-baseline.xlsx'));

        $pinjaman = PumkPinjaman::query()->firstOrFail();
        $pinjaman->saldoAwal()->delete();
        app(PiutangCalculator::class)->bangunUlangBaselineTanpaSaldoAwal($pinjaman);
        $admin = User::factory()->create(['role' => 'pumk_admin']);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2015-06-01',
            'nomor_bukti' => 'BKM-HISTORI-RINCI',
            'pokok' => 50_000,
            'bunga' => 5_000,
            'denda' => 0,
            'created_by' => $admin->id,
        ]);

        $result = $this->invokeImportRow($cells, $this->batch('histori-reimport.xlsx'));

        $this->assertContains('saldo_awal_replaced_by_manual_history', $result['warnings']);
        $this->assertDatabaseCount('pumk_saldo_awal', 0);
        $this->assertDatabaseHas('pumk_angsuran', [
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2015-06-01 00:00:00',
            'nomor_bukti' => 'BKM-HISTORI-RINCI',
        ]);
        $this->assertSame('850000.00', $pinjaman->fresh()->sisa_pokok);
        $this->assertSame('85000.00', $pinjaman->fresh()->sisa_bunga);
    }

    public function test_calculator_comparison_reports_difference_without_overwriting_excel_baseline(): void
    {
        $cells = $this->validCells();
        $cells['AL'] = '999999';

        $result = $this->invokeImportRow($cells, $this->batch('audit-kalkulator.xlsx'));
        $pinjaman = PumkPinjaman::query()->firstOrFail();

        $this->assertContains('calculator_mismatch_total_pokok_masuk', $result['comparison_differences']);
        $this->assertContains('calculator_mismatch_total_pokok_masuk', $result['warnings']);
        $this->assertSame('650000.00', $pinjaman->sisa_pokok);
        $this->assertSame('65000.00', $pinjaman->sisa_bunga);
        $this->assertSame('715000.00', $pinjaman->total_sisa);
    }

    public function test_latest_source_can_correct_contract_when_identity_profile_is_unchanged(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('kontrak-lama.xlsx'));

        $cells['C'] = 'SPJ-KOREKSI';
        $cells['AB'] = '46084';
        $cells['AD'] = '1250000';
        $result = $this->invokeImportRow($cells, $this->batch('kontrak-terbaru.xlsx'));

        $pinjaman = PumkPinjaman::query()->firstOrFail();
        $this->assertContains('source_contract_corrected', $result['warnings']);
        $this->assertSame('SPJ-KOREKSI', $pinjaman->spj_awal);
        $this->assertSame('2026-03-03', $pinjaman->tanggal_pencairan->toDateString());
        $this->assertSame('1250000.00', $pinjaman->pinjaman_pokok);
    }

    public function test_reimport_rejects_material_identity_and_contract_conflict(): void
    {
        $cells = $this->validCells();
        $this->invokeImportRow($cells, $this->batch('profil-lama.xlsx'));

        $cells['B'] = 'Mitra Berbeda';
        $cells['J'] = 'Alamat Berbeda';
        $cells['M'] = '9999999999999999';
        $cells['C'] = 'SPJ-BERBEDA';
        $cells['AB'] = '46084';
        $cells['AD'] = '1250000';

        $this->expectException(PumkImportRowException::class);
        $this->expectExceptionMessage('source_profile_conflict');
        $this->invokeImportRow($cells, $this->batch('profil-konflik.xlsx'));
    }

    public function test_reader_accepts_only_the_final_sheet_and_headers(): void
    {
        $headers = [
            8 => [
                'A' => 'No',
                'B' => 'Nama Mitra Binaan',
                'C' => 'SPJ awal',
                'D' => "Kontrak Reschedulling\nKe-1",
                'G' => 'Kontrak Rescheduling Ke-4',
                'H' => 'Jenis Usaha',
                'P' => 'Jenis jaminan',
                'Z' => 'BERKAS SPJ',
                'AA' => 'BERKAS JAMINAN',
                'AB' => 'Tanggal Pencairan',
                'AD' => "Pinjaman\nPokok",
                'AT' => 'KOLEKTIBILITAS',
                'AX' => 'Angs Pokok',
                'BB' => 'Pokok',
                'CK' => 'Total Angs',
            ],
        ];

        $reader = app(PumkXlsxReader::class);
        $method = new ReflectionMethod($reader, 'assertExpectedWorkbookLayout');
        $method->setAccessible(true);
        $method->invoke($reader, 'Database new versi baseon SPJ', $headers);

        $this->expectException(RuntimeException::class);
        $method->invoke($reader, 'Database Saldo Piutang', $headers);
    }

    /** @return array<string, ?string> */
    private function validCells(): array
    {
        return [
            'A' => '1',
            'B' => 'Mitra Pengujian',
            'C' => 'SPJ-001',
            'D' => null,
            'E' => null,
            'F' => null,
            'G' => null,
            'H' => 'Olahan makanan',
            'I' => 'Industri - Makanan Minuman',
            'J' => 'Jalan Contoh',
            'K' => 'wonogiri',
            'L' => 'Pemilik Contoh',
            'M' => '3512345678901234',
            'N' => '081234567890',
            'O' => '1234567890',
            'AB' => '46053',
            'AC' => '2026',
            'AD' => '1000000',
            'AE' => '10',
            'AF' => '100000',
            'AG' => '1100000',
            'AH' => '46053',
            'AI' => '46418',
            'AK' => '100000',
            'AL' => '350000',
            'AM' => '35000',
            'AN' => '385000',
            'AR' => '1',
            'AS' => '100000',
            'AT' => 'Lancar',
            'AU' => '650000',
            'AV' => '65000',
            'AW' => '715000',
            'AX' => '250000',
            'AY' => '25000',
            'AZ' => '0',
            'BA' => '275000',
            'BB' => '100000',
            'BC' => '10000',
            'BD' => '110000',
        ];
    }

    private function batch(string $file): PumkImportBatch
    {
        return PumkImportBatch::create([
            'nama_file' => $file,
            'file_hash' => hash('sha256', $file),
            'status' => 'processing',
        ]);
    }

    /**
     * @param  array<string, ?string>  $cells
     * @return array<string, mixed>
     */
    private function invokeImportRow(
        array $cells,
        PumkImportBatch $batch,
        ?CarbonImmutable $tanggalAcuan = null,
    ): array {
        $service = app(PumkImportService::class);
        $method = new ReflectionMethod($service, 'importRow');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            $cells,
            $batch,
            $tanggalAcuan ?? CarbonImmutable::create(2026, 7, 31, 0, 0, 0, 'UTC'),
            false,
        );
    }
}
