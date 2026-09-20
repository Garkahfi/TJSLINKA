<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\PumkBriKualitas;
use App\Models\PumkBriRingkasan;
use App\Models\PumkBriSaldoBulanan;
use App\Models\PumkBriSektor;
use App\Models\WilayahOperasional;
use App\Services\Monitoring\MonitoringExcelImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Phar;
use PharData;
use Tests\TestCase;

class MonitoringExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_eight_sheets_are_imported_from_one_workbook(): void
    {
        Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        WilayahOperasional::create([
            'nama' => 'Kab. Pacitan',
            'latitude' => -8.1937,
            'longitude' => 111.1044,
            'realisasi_anggaran' => 123456,
        ]);
        $path = $this->workbook([
            'Pilar' => [
                ['nama_pilar', 'rencana_anggaran', 'realisasi_anggaran'],
                ['Sosial', '1000000', '750000'],
            ],
            'Wilayah' => [
                ['nama_wilayah', 'realisasi_anggaran'],
                ['Kab. Pacitan', '250000'],
            ],
            'BidangPrioritas' => [
                ['nama_bidang', 'rencana_anggaran', 'realisasi_anggaran', 'penyerapan_persen'],
                ['Bidang Pendidikan', '500000', '425000', '85'],
            ],
            'TPB' => [
                ['nomor_tpb', 'nama_tpb', 'rencana_anggaran', 'realisasi_anggaran'],
                ['3', 'Kehidupan Sehat', '400000', '300000'],
            ],
            'PUMK_BRI_Ringkasan' => [
                ['tahun', 'rka_tahun_ini', 'realisasi_sd_desember', 'progres_kolaborasi_persen'],
                ['2026', '125000000', '100000000', '80'],
            ],
            'PUMK_BRI_Sektor' => [
                ['nama_sektor', 'nilai_portofolio'],
                ['Perdagangan', '75000000'],
            ],
            'PUMK_BRI_Kualitas' => [
                ['kategori', 'nilai'],
                ['Kurang Lancar', '5000000'],
            ],
            'PUMK_BRI_SaldoBulanan' => [
                ['sektor_atau_kategori', 'tipe', 'bulan', 'tahun', 'nilai'],
                ['Perdagangan', 'sektor', '7', '2026', '72000000'],
            ],
        ]);

        $summary = app(MonitoringExcelImport::class)->import($path);

        $this->assertSame(
            array_fill_keys(array_keys(MonitoringExcelImport::HEADERS), 'success'),
            collect($summary)->map(fn (array $result): string => $result['status'])->all(),
        );
        $this->assertSame('750000.00', Pillar::where('slug', 'sosial')->firstOrFail()->dashboard_realisasi_anggaran);
        $this->assertSame('250000.00', WilayahOperasional::where('nama', 'Kab. Pacitan')->firstOrFail()->realisasi_anggaran);
        $this->assertDatabaseHas('bidang_prioritas', ['nama_bidang' => 'Bidang Pendidikan', 'penyerapan_persen' => 85]);
        $this->assertDatabaseHas('tpb_dashboard', ['nomor_tpb' => 'TPB 3', 'nama_tpb' => 'Kehidupan Sehat']);
        $this->assertSame('125000000.00', PumkBriRingkasan::findOrFail(1)->rka_tahun_ini);
        $this->assertSame(2026, PumkBriRingkasan::findOrFail(1)->tahun);
        $this->assertSame('75000000.00', PumkBriSektor::where('nama_sektor', 'Perdagangan')->firstOrFail()->nilai_portofolio);
        $this->assertSame('5000000.00', PumkBriKualitas::where('kategori', 'Kurang Lancar')->firstOrFail()->nilai);
        $this->assertSame('72000000.00', PumkBriSaldoBulanan::firstOrFail()->nilai);
    }

    public function test_missing_and_invalid_sheets_do_not_rollback_a_valid_sheet(): void
    {
        Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        WilayahOperasional::create([
            'nama' => 'Kab. Pacitan',
            'latitude' => -8.1937,
            'longitude' => 111.1044,
            'realisasi_anggaran' => 123456,
        ]);
        $path = $this->workbook([
            'Pilar' => [
                ['nama_pilar', 'rencana_anggaran', 'realisasi_anggaran'],
                ['Sosial', '1000000', '800000'],
            ],
            'Wilayah' => [
                ['nama_wilayah', 'realisasi_anggaran'],
                ['Kab. Pacitan', 'bukan angka'],
            ],
        ]);

        $summary = app(MonitoringExcelImport::class)->import($path);

        $this->assertSame('success', $summary['Pilar']['status']);
        $this->assertSame('failed', $summary['Wilayah']['status']);
        $this->assertSame('skipped', $summary['TPB']['status']);
        $this->assertSame('800000.00', Pillar::where('slug', 'sosial')->firstOrFail()->dashboard_realisasi_anggaran);
        $this->assertSame('123456.00', WilayahOperasional::where('nama', 'Kab. Pacitan')->firstOrFail()->realisasi_anggaran);
    }

    /** @param array<string, list<list<string>>> $sheets */
    private function workbook(array $sheets): string
    {
        $base = tempnam(sys_get_temp_dir(), 'monitoring-xlsx-');
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
                $column = chr(65 + $columnIndex);
                $cells[] = '<c r="'.$column.$number.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
            }
            $rowNodes[] = '<row r="'.$number.'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $rowNodes).'</sheetData></worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
