<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Services\Pumk\PumkPiutangReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PumkPiutangReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_explains_payment_delta_and_unknown_closing_balance_without_writing(): void
    {
        $mitra = PumkMitra::create(['nama_mitra' => 'Fixture Audit', 'source_key' => 'audit-owner']);
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'db-pumk-v1|pinjaman|1'),
            'status' => 'aktif', 'is_active' => true, 'pinjaman_pokok' => '200000000.00',
            'pinjaman_bunga' => '0.00', 'total_sisa' => '186664000.00', 'kolektibilitas' => 'kurang_lancar',
            'source_updated_at' => '2026-07-31 12:00:00', 'nilai_angsuran_bulanan' => '3334000.00',
            'baseline_sumber' => [
                'sisa_pokok' => '186664000.00', 'sisa_bunga' => '0.00',
                'bulan_tunggakan' => 3, 'nilai_tunggakan' => '10002000.00', 'kolektibilitas' => 'kurang_lancar',
                'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00', 'total_denda_masuk' => '0.00',
            ],
        ]);
        PumkAngsuran::create([
            'pinjaman_id' => $loan->id, 'periode' => '2026-08-01', 'pokok' => '3334000.00',
            'bunga' => '0.00', 'denda' => '0.00', 'created_at' => '2026-08-01 12:00:00',
        ]);
        DB::table('pumk_pinjaman')->where('id', $loan->id)->update(['total_sisa' => '186664000.00']);
        $closed = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'db-pumk-v1|pinjaman|2'),
            'status' => 'lunas', 'is_active' => false, 'kolektibilitas' => 'lancar',
            // A legacy cached zero without source components is not proof of
            // a zero financial position for this closed loan.
            'total_sisa' => '0.00',
            'lunas_at' => '2026-09-01 12:00:00',
        ]);
        $workbook = $this->workbook([
            ['A' => '1.0', 'AT' => 'Kurang Lancar', 'AU' => '186664000', 'AV' => '0', 'AW' => '186664000'],
            ['A' => '2', 'AT' => 'Lancar', 'AU' => '-193232', 'AV' => '0', 'AW' => '-193232'],
            ['A' => '3', 'AT' => 'Macet', 'AU' => '10', 'AV' => '0', 'AW' => '10'],
        ]);
        $before = DB::table('pumk_pinjaman')->orderBy('id')->get()->toJson();
        $payments = DB::table('pumk_angsuran')->orderBy('id')->get()->toJson();

        $result = app(PumkPiutangReconciliationService::class)->reconcile($workbook);

        $row = $result['loans'][0];
        $this->assertSame('183330000.00', $row['card_total']);
        $this->assertSame('-3334000.00', $row['recap_minus_source_aw']);
        $this->assertSame('3334000.00', $row['net_payment_change_from_baseline']['pokok']);
        $this->assertContains('cache_differs_from_card', $row['flags']);
        $this->assertCount(1, $row['payments']);
        $this->assertNull($result['loans'][1]['recap_total']);
        $this->assertSame($closed->id, $result['loans'][1]['pinjaman_id']);
        $this->assertSame(1, $result['recap']['belum_dinilai']['unknown_balances']);
        $this->assertSame(3, $result['source_rows_missing_in_database'][0]['source_no']);
        $this->assertSame($before, DB::table('pumk_pinjaman')->orderBy('id')->get()->toJson());
        $this->assertSame($payments, DB::table('pumk_angsuran')->orderBy('id')->get()->toJson());
    }

    public function test_audit_shows_source_component_rounding_and_does_not_mark_unscoped_rows_as_missing(): void
    {
        $mitra = PumkMitra::create(['nama_mitra' => 'Fixture Sen', 'source_key' => 'audit-cents']);
        PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'db-pumk-v1|pinjaman|1'),
            'pinjaman_pokok' => '100.00', 'pinjaman_bunga' => '0.00',
            'total_sisa' => '100.00', 'status' => 'aktif', 'is_active' => true,
        ]);
        $result = app(PumkPiutangReconciliationService::class)->reconcile($this->workbook([
            ['A' => '1', 'AT' => 'Lancar', 'AU' => '100.129', 'AV' => '0.019', 'AW' => '100.148'],
            ['A' => '2', 'AT' => 'Lancar', 'AW' => '200'],
        ]), $mitra->id);

        $this->assertSame('-0.01', $result['loans'][0]['source']['components_minus_aw']);
        $this->assertSame('300.15', $result['source_subtotals']['lancar']['display_total']);
        $this->assertSame([], $result['source_rows_missing_in_database']);
    }

    public function test_duplicate_source_ids_stop_reconciliation_instead_of_guessing_by_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplikat');
        app(PumkPiutangReconciliationService::class)->reconcile($this->workbook([['A' => '1'], ['A' => '1.00']]));
    }

    private function workbook(array $cells): array
    {
        return [
            'sheet_name' => 'Database new versi baseon SPJ', 'tanggal_acuan_raw' => '46234',
            'rows' => array_map(fn ($row, $index) => [
                'worksheet_row' => $index + 9, 'cells' => $row, 'source_warnings' => [],
            ], $cells, array_keys($cells)),
        ];
    }
}
