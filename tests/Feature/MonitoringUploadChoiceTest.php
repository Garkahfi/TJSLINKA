<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\PumkBriFasilitas;
use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRingkasan;
use App\Models\PumkBriRkaTahunan;
use App\Models\PumkBriSnapshotBulanan;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Phar;
use PharData;
use Tests\TestCase;

class MonitoringUploadChoiceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/monitoring/upload';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'pumk_admin',
            'is_active' => true,
            'must_change_password' => false,
        ]), 'pumk');
    }

    public function test_type_and_file_are_required_and_unknown_type_cannot_write(): void
    {
        $this->post(self::URL)->assertSessionHasErrors(['import_type', 'monitoring_file']);
        $this->post(self::URL, [
            'import_type' => 'other',
            'monitoring_file' => $this->file($this->workbook(['Pilar' => $this->pilarRows()])),
        ])->assertSessionHasErrors('import_type');

        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 0);
        $this->assertDatabaseCount('pumk_activity_logs', 0);
    }

    public function test_file_format_corruption_and_size_are_rejected(): void
    {
        $this->post(self::URL, [
            'import_type' => 'tjsl',
            'monitoring_file' => UploadedFile::fake()->createWithContent('wrong.txt', 'not excel'),
        ])->assertSessionHasErrors('monitoring_file');
        $this->post(self::URL, [
            'import_type' => 'tjsl',
            'monitoring_file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'not excel'),
        ])->assertSessionHasErrors('monitoring_file');
        $this->post(self::URL, [
            'import_type' => 'tjsl',
            'monitoring_file' => UploadedFile::fake()->createWithContent('large.xlsx', str_repeat('x', 10 * 1024 * 1024 + 1)),
        ])->assertSessionHasErrors('monitoring_file');

        $this->assertDatabaseCount('pumk_activity_logs', 0);
    }

    public function test_tjsl_only_imports_selected_sheet_and_preserves_bri_data(): void
    {
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        PumkBriRingkasan::create([
            'tahun' => 2026, 'rka_tahun_ini' => 100, 'realisasi_sd_desember' => 0,
            'progres_kolaborasi_persen' => 0,
        ]);
        $path = $this->workbook([
            'Pilar' => $this->pilarRows(),
            'PUMK_BRI_Ringkasan' => [
                ['tahun', 'rka_tahun_ini', 'realisasi_sd_desember', 'progres_kolaborasi_persen'],
                ['2026', '999', '999', '100'],
            ],
            'Jan' => $this->briRows(),
        ]);

        $this->post(self::URL, [
            'import_type' => 'tjsl',
            'default_year' => 'not-a-year',
            'monitoring_file' => $this->file($path),
        ])->assertSessionHas('import_result.summary.Pilar.status', 'success')
            ->assertSessionHas('import_result.summary.Wilayah.status', 'skipped')
            ->assertSessionHas('import_result.level', 'success');

        $this->assertSame('750.00', $pillar->fresh()->dashboard_realisasi_anggaran);
        $this->assertSame('100.00', PumkBriRingkasan::firstOrFail()->rka_tahun_ini);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 0);
        $this->assertDatabaseHas('pumk_activity_logs', ['module' => 'tjsl', 'actor_role' => 'pumk_admin']);
    }

    public function test_tjsl_partial_rows_are_reported_without_rolling_back_valid_row(): void
    {
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $rows = $this->pilarRows();
        $rows[] = ['Pilar Tidak Terdaftar', '1000', '750'];

        $this->post(self::URL, [
            'import_type' => 'tjsl',
            'monitoring_file' => $this->file($this->workbook(['Pilar' => $rows])),
        ])->assertSessionHas('import_result.summary.Pilar.status', 'partial')
            ->assertSessionHas('import_result.summary.Pilar.berhasil', 1)
            ->assertSessionHas('import_result.summary.Pilar.gagal', 1)
            ->assertSessionHas('import_result.level', 'warning');

        $this->assertSame('750.00', $pillar->fresh()->dashboard_realisasi_anggaran);
    }

    public function test_bri_only_imports_snapshots_with_effective_year_and_preserves_tjsl(): void
    {
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $path = $this->workbook([
            'Pilar' => $this->pilarRows(),
            'Jan' => $this->briRows(),
            'Feb 2027' => $this->briRows('Mitra Dua*', '600'),
        ]);

        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot',
            'default_year' => '2026',
            'monitoring_file' => $this->file($path),
        ])->assertSessionHas('import_result.summary.Jan.status', 'imported')
            ->assertSessionHas('import_result.summary.Jan.tahun', 2026)
            ->assertSessionHas('import_result.summary.Feb 2027.tahun', 2027)
            ->assertSessionHas('import_result.level', 'success');

        $this->assertSame('0.00', $pillar->fresh()->dashboard_realisasi_anggaran);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['tahun' => 2026, 'bulan' => 1, 'saldo_piutang' => 500]);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['tahun' => 2027, 'bulan' => 2, 'saldo_piutang' => 600]);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 2);
        $this->assertDatabaseCount('pumk_bri_rka_tahunan', 0);
        $this->assertDatabaseCount('pumk_bri_penyaluran_bulanan', 0);
        $this->assertDatabaseCount('pumk_mitra', 0);
        $this->assertDatabaseHas('pumk_activity_logs', ['module' => 'pumk_bri', 'actor_role' => 'pumk_admin']);
    }

    public function test_wrong_choice_and_wrong_headers_reject_before_status_sync(): void
    {
        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Pilar' => $this->pilarRows()])),
        ])->assertSessionHasErrors('monitoring_file');
        $this->post(self::URL, [
            'import_type' => 'tjsl',
            'monitoring_file' => $this->file($this->workbook(['Jan' => $this->briRows()])),
        ])->assertSessionHasErrors('monitoring_file');
        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Jan' => [['No', 'Nama Mitra Binaan']]])),
        ])->assertSessionHasErrors('monitoring_file');

        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 0);
        $this->assertDatabaseCount('pumk_bri_fasilitas', 0);
        $this->assertDatabaseCount('pumk_activity_logs', 0);
    }

    public function test_wrong_bri_workbook_does_not_resynchronize_existing_facility_status(): void
    {
        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Jan' => $this->briRows('Mitra Satu*', '0')])),
        ])->assertSessionHas('import_result.level', 'success');
        $facility = PumkBriFasilitas::firstOrFail();
        $this->assertSame('lunas', $facility->status);
        $facility->update(['status' => 'aktif']);

        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Pilar' => $this->pilarRows()])),
        ])->assertSessionHasErrors('monitoring_file');

        $this->assertSame('aktif', $facility->fresh()->status);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 1);
    }

    public function test_bri_year_is_required_and_out_of_range_rejected(): void
    {
        foreach ([null, '1899', 'next year'] as $year) {
            $this->post(self::URL, [
                'import_type' => 'pumk_bri_snapshot',
                'default_year' => $year,
                'monitoring_file' => $this->file($this->workbook(['Jan' => $this->briRows()])),
            ])->assertSessionHasErrors('default_year');
        }

        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 0);
    }

    public function test_existing_period_is_skipped_even_if_client_sends_force(): void
    {
        $first = $this->workbook(['Jan' => $this->briRows()]);
        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($first),
        ])->assertSessionHas('import_result.level', 'success');

        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026', 'force' => '1',
            'monitoring_file' => $this->file($this->workbook(['Jan' => $this->briRows('Mitra Satu*', '900')])),
        ])->assertSessionHas('import_result.summary.Jan.status', 'skipped')
            ->assertSessionHas('import_result.level', 'info');

        $this->assertSame('500.00', PumkBriSnapshotBulanan::firstOrFail()->saldo_piutang);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 1);
    }

    public function test_invalid_total_plus_valid_period_reports_partial_and_does_not_write_failed_period(): void
    {
        $bad = $this->briRows();
        $bad[0][7] = '999';
        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Jan' => $bad, 'Feb' => $this->briRows('Mitra Dua*', '600')])),
        ])->assertSessionHas('import_result.summary.Jan.status', 'failed')
            ->assertSessionHas('import_result.summary.Feb.status', 'imported')
            ->assertSessionHas('import_result.level', 'warning');

        $this->assertDatabaseMissing('pumk_bri_snapshot_bulanan', ['bulan' => 1, 'tahun' => 2026]);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['bulan' => 2, 'tahun' => 2026]);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 1);
    }

    public function test_all_failed_result_is_not_reported_as_success(): void
    {
        $bad = $this->briRows();
        $bad[0][7] = '999';
        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Jan' => $bad])),
        ])->assertSessionHas('import_result.level', 'error')
            ->assertSessionHas('import_result.summary.Jan.status', 'failed');

        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 0);
    }

    public function test_ambiguous_identity_needs_review_and_does_not_claim_snapshot_success(): void
    {
        $reviewRows = $this->briRows('Mitra Tetap', '400');
        foreach ([2, 3, 4, 5, 6] as $column) {
            $reviewRows[2][$column] = '';
        }

        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook([
                'Jan' => $this->briRows('Mitra Tetap*'),
                'Feb' => $reviewRows,
            ])),
        ])->assertSessionHas('import_result.summary.Jan.status', 'imported')
            ->assertSessionHas('import_result.summary.Feb.status', 'needs_review')
            ->assertSessionHas('import_result.level', 'warning');

        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 1);
        $this->assertDatabaseCount('pumk_bri_identity_reviews', 1);
        $this->assertDatabaseMissing('pumk_bri_snapshot_bulanan', ['tahun' => 2026, 'bulan' => 2]);
    }

    public function test_upload_preserves_existing_rka_penyaluran_and_internal_card_data(): void
    {
        PumkBriRkaTahunan::create(['tahun' => 2026, 'nominal_rka' => 0]);
        PumkBriPenyaluranBulanan::create(['tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => 0]);
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Internal', 'source_key' => str_repeat('a', 64)]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => str_repeat('b', 64),
            'pinjaman_pokok' => 1500, 'sisa_pokok' => 1200,
        ]);

        $this->post(self::URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2026',
            'monitoring_file' => $this->file($this->workbook(['Jan' => $this->briRows()])),
        ])->assertSessionHas('import_result.level', 'success');

        $this->assertSame('0.00', PumkBriRkaTahunan::firstOrFail()->nominal_rka);
        $this->assertSame('0.00', PumkBriPenyaluranBulanan::firstOrFail()->nominal_penyaluran);
        $this->assertDatabaseCount('pumk_bri_rka_tahunan', 1);
        $this->assertDatabaseCount('pumk_bri_penyaluran_bulanan', 1);
        $this->assertSame('Mitra Internal', $mitra->fresh()->nama_mitra);
        $this->assertSame('1200.00', $pinjaman->fresh()->sisa_pokok);
        $this->assertDatabaseCount('pumk_mitra', 1);
        $this->assertDatabaseCount('pumk_pinjaman', 1);
    }

    /** @return list<list<string>> */
    private function pilarRows(): array
    {
        return [
            ['nama_pilar', 'rencana_anggaran', 'realisasi_anggaran'],
            ['Sosial', '1000', '750'],
        ];
    }

    /** @return list<list<string>> */
    private function briRows(string $name = 'Mitra Satu*', string $balance = '500'): array
    {
        return [
            ['', '', '', '', '', '', '', $balance, ''],
            ['No', 'Nama Mitra Binaan', 'Alamat', 'Wilayah', 'Sektor Usaha', 'Pinjaman', 'Tenor', 'Saldo Piutang', 'Kolektibilitas'],
            ['1', $name, 'Alamat sumber', 'Madiun', 'Jasa', '1000', '12 M', $balance, 'L'],
        ];
    }

    private function file(string $path): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('monitoring.xlsx', file_get_contents($path));
    }

    /** @param array<string, list<list<string>>> $sheets */
    private function workbook(array $sheets): string
    {
        $base = tempnam(sys_get_temp_dir(), 'monitoring-choice-');
        if ($base === false) {
            $this->fail('Tidak dapat membuat file XLSX sementara.');
        }
        @unlink($base);
        $zipPath = $base.'.zip';
        $xlsxPath = $base.'.xlsx';
        $archive = new PharData($zipPath, 0, null, Phar::ZIP);
        $sheetNodes = [];
        $relationships = [];
        foreach ($sheets as $name => $rows) {
            $index = count($sheetNodes) + 1;
            $sheetNodes[] = '<sheet name="'.$this->xml($name).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
            $relationships[] = '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
            $archive->addFromString('xl/worksheets/sheet'.$index.'.xml', $this->worksheetXml($rows));
        }
        $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .implode('', $sheetNodes).'</sheets></workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', $relationships).'</Relationships>');
        unset($archive);
        rename($zipPath, $xlsxPath);
        $this->beforeApplicationDestroyed(static fn () => @unlink($xlsxPath));

        return $xlsxPath;
    }

    /** @param list<list<string>> $rows */
    private function worksheetXml(array $rows): string
    {
        $nodes = [];
        foreach ($rows as $rowIndex => $values) {
            $cells = [];
            foreach ($values as $columnIndex => $value) {
                if ($value !== '') {
                    $cells[] = '<c r="'.chr(65 + $columnIndex).($rowIndex + 1).'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
                }
            }
            $nodes[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $nodes).'</sheetData></worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
