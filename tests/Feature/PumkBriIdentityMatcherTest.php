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

class PumkBriIdentityMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_consistent_multi_month_profile_with_changed_due_date_maps_to_one_mitra_and_facility(): void
    {
        $path = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra Konsisten *', 'Jl. Mawar 10', 'Kota Madiun', 'Perdagangan', '10000000', '36 M', '15/01/2026', '15/01/2029', '9000000', 'Lancar (L)'],
            ]),
            'Feb' => $this->sheetRows(2, [
                ['1', 'Mitra Konsisten*', 'Jl. Mawar 10', 'Kota Madiun', 'Perdagangan', '10000000', '36 M', '15/01/2026', '15/02/2029', '8000000', 'Kurang Lancar (KL)'],
            ]),
        ]);

        $result = app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $this->assertSame('imported', $result['Jan']['status']);
        $this->assertSame('imported', $result['Feb']['status']);
        $this->assertSame(1, PumkBriMitra::count());
        $this->assertSame(1, PumkBriFasilitas::count());
        $this->assertSame(2, PumkBriSnapshotBulanan::count());
        $this->assertSame(0, PumkBriIdentityReview::count());

        $mitra = PumkBriMitra::firstOrFail();
        $facility = PumkBriFasilitas::firstOrFail();
        $february = PumkBriSnapshotBulanan::query()->where('bulan', 2)->firstOrFail();

        $this->assertSame($mitra->id, $facility->mitra_id);
        $this->assertSame($facility->id, $february->fasilitas_id);
        $this->assertSame('Mitra Konsisten *', $mitra->nama_mitra);
        $this->assertNull($february->tanggal_jatuh_tempo_sumber);
        $this->assertSame('15/02/2029', $february->source_payload['tanggal_jatuh_tempo']);
        $this->assertSame('Mitra Konsisten*', $february->nama_mitra_sumber);
    }

    public function test_same_name_with_clearly_distinct_profiles_remains_distinct_without_review(): void
    {
        $path = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mariyat*', 'Jl. Melati 1', 'Kota Madiun', 'Perdagangan', '10000000', '12 M', '01/01/2026', '01/01/2027', '9000000', 'L'],
                ['2', 'Mariyat *', 'Dusun Sumber 9', 'Kab. Magetan', 'Pertanian', '25000000', '24 M', '15/02/2026', '15/02/2028', '23000000', 'L'],
            ]),
        ]);

        $result = app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $this->assertSame('imported', $result['Jan']['status']);
        $this->assertSame(2, PumkBriMitra::count());
        $this->assertSame(2, PumkBriFasilitas::count());
        $this->assertSame(2, PumkBriSnapshotBulanan::count());
        $this->assertSame(0, PumkBriIdentityReview::count());

        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'nama_mitra_sumber' => 'Mariyat*',
            'alamat_sumber' => 'Jl. Melati 1',
            'wilayah_sumber' => 'Kota Madiun',
            'pinjaman_sumber' => 10000000,
        ]);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'nama_mitra_sumber' => 'Mariyat *',
            'alamat_sumber' => 'Dusun Sumber 9',
            'wilayah_sumber' => 'Kab. Magetan',
            'pinjaman_sumber' => 25000000,
        ]);
    }

    public function test_address_variation_does_not_split_a_consistent_mitra_and_facility(): void
    {
        $path = $this->workbook([
            'Jan' => $this->sheetRows(3, [
                ['1', 'Mitra Ambigu*', 'Jl. Kenanga 22', 'Kota Madiun', 'Jasa', '12000000', '24 M', '01/01/2026', '01/01/2028', '11000000', 'L'],
            ]),
            'Feb' => $this->sheetRows(2, [
                ['1', 'Mitra Ambigu*', 'Jl. Kenanga 22 Blok B', 'Kota Madiun', 'Jasa', '12000000', '24 M', '01/01/2026', '01/01/2028', '10000000', 'L'],
            ]),
        ]);

        $result = app(PumkBriSnapshotImportService::class)->import($path, 2026);

        $this->assertSame('imported', $result['Jan']['status']);
        $this->assertSame('imported', $result['Feb']['status']);
        $this->assertSame(1, PumkBriMitra::count());
        $this->assertSame(1, PumkBriFasilitas::count());
        $this->assertSame(2, PumkBriSnapshotBulanan::count());
        $this->assertSame(0, PumkBriIdentityReview::count());
        $this->assertSame('Jl. Kenanga 22', PumkBriMitra::firstOrFail()->alamat);
        $this->assertSame('Jl. Kenanga 22 Blok B', PumkBriSnapshotBulanan::query()->where('bulan', 2)->firstOrFail()->alamat_sumber);
    }

    public function test_identity_review_command_groups_equivalent_source_profiles_for_manual_review(): void
    {
        $mitra = PumkBriMitra::create([
            'nama_mitra' => 'Kandidat Review',
            'source_key' => hash('sha256', 'kandidat-review'),
        ]);
        $candidate = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => '2b84bc22-75cf-462d-b9d3-f8175d0c36f8',
        ]);
        $profile = [
            'nama_mitra' => 'Mitra Review*',
            'alamat' => 'Jl. Kenanga 22 Blok B',
            'wilayah' => 'Kota Madiun',
            'sektor_usaha' => 'Jasa',
            'pinjaman' => '12000000.00',
            'tenor_raw' => '24 M',
            'tanggal_pencairan' => '2026-01-01',
        ];

        foreach ([[2, '2028-02-01'], [3, '2028-03-01']] as [$month, $dueDate]) {
            PumkBriIdentityReview::create([
                'tahun' => 2026,
                'bulan' => $month,
                'source_sheet' => $month === 2 ? 'Feb' : 'Mar',
                'source_row' => 10,
                'source_profile' => $profile + ['tanggal_jatuh_tempo' => $dueDate],
                'source_fingerprint' => hash('sha256', $month.$dueDate),
                'candidate_fasilitas_ids' => [$candidate->id],
                'reason' => 'Profil perlu divalidasi.',
                'status' => 'pending',
            ]);
        }

        $this->artisan('pumkbri:review-identities', ['--year' => 2026])
            ->expectsOutputToContain('Mitra Review*')
            ->expectsOutputToContain('Menampilkan 1 grup dari 2 baris review.')
            ->expectsOutputToContain('Tiap grup hanya membantu peninjauan.')
            ->assertExitCode(0);
    }

    public function test_resolution_command_can_apply_an_explicit_choice_to_same_profile_reviews(): void
    {
        $mitra = PumkBriMitra::create([
            'nama_mitra' => 'Kandidat Resolusi',
            'source_key' => hash('sha256', 'kandidat-resolusi'),
        ]);
        $candidate = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => '0c5118d1-4a87-43df-af13-74a1ee087613',
        ]);
        $profile = [
            'nama_mitra' => 'Mitra Resolusi*',
            'alamat' => 'Jl. Mawar 1',
            'wilayah' => 'Kota Madiun',
            'sektor_usaha' => 'Perdagangan',
            'pinjaman' => '25000000.00',
            'tenor_raw' => '36 M',
            'tanggal_pencairan' => '2024-03-07',
        ];
        $reviews = collect([[1, '2027-01-01'], [2, '2027-02-01']])->map(
            function (array $source) use ($profile, $candidate): PumkBriIdentityReview {
                [$month, $dueDate] = $source;

                return PumkBriIdentityReview::create([
                    'tahun' => 2026,
                    'bulan' => $month,
                    'source_sheet' => $month === 1 ? 'Jan' : 'Feb',
                    'source_row' => 20,
                    'source_profile' => $profile + ['tanggal_jatuh_tempo' => $dueDate],
                    'source_fingerprint' => hash('sha256', 'resolusi'.$month),
                    'candidate_fasilitas_ids' => [$candidate->id],
                    'reason' => 'Profil perlu divalidasi.',
                    'status' => 'pending',
                ]);
            },
        );

        $this->artisan('pumkbri:resolve-identity', [
            'review' => $reviews->first()->id,
            '--fasilitas' => $candidate->id,
            '--same-profile' => true,
        ])
            ->expectsOutputToContain('Keputusan review tersimpan untuk 2 baris.')
            ->assertExitCode(0);

        $this->assertSame(2, PumkBriIdentityReview::query()->where('status', 'resolved')->count());
        $this->assertSame(0, PumkBriIdentityReview::query()->where('status', 'pending')->count());
        $this->assertSame(
            [$candidate->id, $candidate->id],
            PumkBriIdentityReview::query()->orderBy('id')->pluck('resolved_fasilitas_id')->map(fn ($id) => (int) $id)->all(),
        );
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
        $base = tempnam(sys_get_temp_dir(), 'pumk-bri-identity-');
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
