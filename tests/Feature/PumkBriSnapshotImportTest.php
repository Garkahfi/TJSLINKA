<?php

namespace Tests\Feature;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use App\Services\Pumk\PumkBriSnapshotImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Phar;
use PharData;
use Tests\TestCase;

class PumkBriSnapshotImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_columns_are_optional_and_raw_dates_are_not_identity_fields(): void
    {
        $rows = [
            ['', '', '', '', '', '', '', '9000000', ''],
            ['No', 'Nama Mitra Binaan', 'Alamat', 'Wilayah', 'Sektor Usaha', 'Pinjaman', 'Tenor', 'Saldo Piutang', 'Kolektibilitas'],
            ['1', 'Mitra Tanpa Tanggal*', 'Jl. Mawar', 'Madiun', 'Jasa', '10000000', '12 M', '9000000', 'L'],
        ];
        $result = app(PumkBriSnapshotImportService::class)->import($this->workbook(['Jan' => $rows]), 2026);

        $this->assertSame('imported', $result['Jan']['status']);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['tahun' => 2026, 'bulan' => 1, 'saldo_piutang' => 9000000]);
        $this->assertNull(PumkBriFasilitas::firstOrFail()->tanggal_pencairan);
    }

    public function test_facility_missing_from_latest_complete_period_is_marked_paid_off(): void
    {
        $path = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra Bertahan*', '', 'Madiun', 'Jasa', '5000000', '12 M', '', '', '4000000', 'L'],
                ['2', 'Mitra Selesai*', '', 'Ngawi', 'Jasa', '6000000', '12 M', '', '', '5000000', 'L'],
            ]),
            'Feb' => $this->sheetRows(2, [
                ['1', 'Mitra Bertahan*', '', 'Madiun', 'Jasa', '5000000', '12 M', '', '', '3000000', 'L'],
            ]),
        ]);
        app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $this->assertSame('aktif', PumkBriFasilitas::query()->whereHas('mitra', fn ($q) => $q->where('nama_mitra', 'Mitra Bertahan*'))->value('status'));
        $this->assertSame('lunas', PumkBriFasilitas::query()->whereHas('mitra', fn ($q) => $q->where('nama_mitra', 'Mitra Selesai*'))->value('status'));
    }

    public function test_it_imports_monthly_sheets_with_variable_header_rows_and_preserves_source_names(): void
    {
        $path = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra Sejahtera *', 'Jl. Mawar', 'Madiun', 'Perdagangan', '10000000', '36 M', '15/01/2026', '15/01/2029', '9000000', 'Lancar (L)'],
            ]),
            'Feb' => $this->sheetRows(2, [
                ['1', 'Mitra Sejahtera*', 'Jl. Mawar', 'Madiun', 'Perdagangan', '10000000', '36 M', '15/01/2026', '47133', '8000000', 'Kurang Lancar (KL)'],
            ]),
            'Catatan' => [['Sheet ini bukan data bulanan']],
        ]);

        $result = app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $this->assertSame('imported', $result['Jan']['status']);
        $this->assertSame('imported', $result['Feb']['status']);
        $this->assertSame('ignored', $result['Catatan']['status']);
        $this->assertSame(1, PumkBriMitra::count());
        $this->assertSame(2, PumkBriSnapshotBulanan::count());

        $mitra = PumkBriMitra::firstOrFail();
        $fasilitas = PumkBriFasilitas::firstOrFail();
        $this->assertSame('Mitra Sejahtera *', $mitra->nama_mitra);
        $this->assertSame($mitra->id, $fasilitas->mitra_id);
        $this->assertNull($fasilitas->tanggal_pencairan);
        $this->assertSame('15/01/2026', PumkBriSnapshotBulanan::query()
            ->where('bulan', 1)->firstOrFail()->source_payload['tanggal_pencairan']);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'mitra_id' => $mitra->id,
            'bulan' => 2,
            'tahun' => 2026,
            'saldo_piutang' => 8000000,
            'kolektibilitas_kode' => 'KL',
            'kolektibilitas_label' => 'Kurang Lancar',
        ]);
    }

    public function test_existing_period_is_skipped_and_force_replaces_all_rows_in_that_period(): void
    {
        $firstPath = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra A*', '', 'Madiun', 'Jasa', '5000000', '12 M', '01/01/2026', '01/01/2027', '4000000', 'L'],
                ['2', 'Mitra B*', '', 'Ngawi', 'Industri', '6000000', '12 M', '02/01/2026', '02/01/2027', '5000000', 'D'],
            ]),
        ]);
        app(PumkBriSnapshotImportService::class)->import($firstPath, 2026);

        $replacementPath = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra A*', '', 'Madiun', 'Jasa', '5000000', '12 M', '01/01/2026', '01/01/2027', '3500000', 'L'],
            ]),
        ]);

        $skipped = app(PumkBriSnapshotImportService::class)->import($replacementPath, 2026);
        $this->assertSame('skipped', $skipped['Jan']['status']);
        $this->assertSame(2, PumkBriSnapshotBulanan::count());

        $forced = app(PumkBriSnapshotImportService::class)->import($replacementPath, 2026, true);
        $this->assertSame('imported', $forced['Jan']['status']);
        $this->assertSame(1, PumkBriSnapshotBulanan::where('bulan', 1)->where('tahun', 2026)->count());
        $this->assertSame('3500000.00', PumkBriSnapshotBulanan::firstOrFail()->saldo_piutang);
    }

    public function test_invalid_sheet_does_not_rollback_another_valid_month_and_explicit_year_is_used(): void
    {
        $invalidRows = $this->sheetRows(3, [
            ['1', 'Mitra Salah*', '', 'Madiun', 'Jasa', '5000000', '12 M', '01/01/2026', '01/01/2027', '4000000', 'L'],
        ]);
        $invalidRows[1][9] = '9999999';

        $path = $this->workbook([
            'Jan' => $invalidRows,
            'Jan 2027' => $this->sheetRows(2, [
                ['1', 'Mitra Baru*', '', 'Madiun', 'Jasa', '7000000', '12 M', '01/01/2027', '01/01/2028', '6500000', 'M'],
            ]),
        ]);

        $result = app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $this->assertSame('failed', $result['Jan']['status']);
        $this->assertStringContainsString('tidak cocok', $result['Jan']['pesan']);
        $this->assertSame('imported', $result['Jan 2027']['status']);
        $this->assertDatabaseMissing('pumk_bri_snapshot_bulanan', ['tahun' => 2026]);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['bulan' => 1, 'tahun' => 2027]);
    }

    public function test_insufficient_profile_data_is_held_for_review_without_erasing_existing_values(): void
    {
        $path = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra Tetap*', 'Alamat lengkap', 'Madiun', 'Jasa', '5000000', '12 M', '01/01/2026', '01/01/2027', '4000000', 'L'],
            ]),
            'Feb' => $this->sheetRows(2, [
                ['1', 'Mitra Tetap', '', '', '', '', '', '01/01/2026', '', '3500000', 'L'],
            ]),
        ]);

        $result = app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $mitra = PumkBriMitra::firstOrFail();
        $this->assertSame('imported', $result['Jan']['status']);
        $this->assertSame('needs_review', $result['Feb']['status']);
        $this->assertSame('Alamat lengkap', $mitra->alamat);
        $this->assertSame('Madiun', $mitra->wilayah);
        $this->assertSame('Jasa', $mitra->sektor_usaha);
        $this->assertSame('5000000.00', $mitra->pinjaman);
        $this->assertSame('12 M', $mitra->tenor_raw);
        $this->assertNull($mitra->tanggal_jatuh_tempo);
        $this->assertSame(1, PumkBriSnapshotBulanan::count());
        $this->assertSame(1, PumkBriIdentityReview::count());
    }

    /**
     * @param  list<list<string>>  $records
     * @return list<list<string>>
     */
    private function sheetRows(int $headerRow, array $records): array
    {
        $headers = [
            'No',
            'Nama Mitra Binaan',
            'Alamat',
            'Wilayah',
            'Sektor Usaha',
            'Pinjaman',
            'Tenor',
            'Tanggal pencairan',
            "Tanggal Jatuh Tempo'",
            'Saldo Piutang',
            'Kolektibilitas',
        ];
        $total = array_sum(array_map(static fn (array $record): float => (float) $record[9], $records));
        $rows = array_fill(0, $headerRow - 1, []);
        $rows[$headerRow - 2][9] = (string) $total;
        $rows[] = $headers;

        return array_merge($rows, $records);
    }

    /** @param array<string, list<list<string>>> $sheets */
    private function workbook(array $sheets): string
    {
        $base = tempnam(sys_get_temp_dir(), 'pumk-bri-xlsx-');
        if ($base === false) {
            $this->fail('Tidak dapat membuat file XLSX sementara.');
        }
        @unlink($base);
        $zipPath = $base.'.zip';
        $xlsxPath = $base.'.xlsx';
        $archive = new PharData($zipPath, 0, null, Phar::ZIP);
        $sheetNodes = [];
        $relationshipNodes = [];
        $sheetIndex = 1;

        foreach ($sheets as $name => $rows) {
            $sheetNodes[] = '<sheet name="'.$this->xml($name).'" sheetId="'.$sheetIndex.'" r:id="rId'.$sheetIndex.'"/>';
            $relationshipNodes[] = '<Relationship Id="rId'.$sheetIndex.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheetIndex.'.xml"/>';
            $archive->addFromString('xl/worksheets/sheet'.$sheetIndex.'.xml', $this->worksheetXml($rows));
            $sheetIndex++;
        }

        $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .implode('', $sheetNodes).'</sheets></workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', $relationshipNodes).'</Relationships>');
        unset($archive);
        rename($zipPath, $xlsxPath);
        $this->beforeApplicationDestroyed(static fn () => @unlink($xlsxPath));

        return $xlsxPath;
    }

    /** @param list<list<string>> $rows */
    private function worksheetXml(array $rows): string
    {
        $rowNodes = [];
        foreach ($rows as $rowIndex => $values) {
            $number = $rowIndex + 1;
            $cells = [];
            foreach ($values as $columnIndex => $value) {
                if ($value === '') {
                    continue;
                }
                $column = $this->columnName($columnIndex + 1);
                $cells[] = '<c r="'.$column.$number.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
            }
            $rowNodes[] = '<row r="'.$number.'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $rowNodes).'</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
