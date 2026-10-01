<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pumk\PumkBriWorkbookReader;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PharData;
use Tests\TestCase;

class PumkBriTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const DOWNLOAD_URL = '/admin/monitoring/template/pumk-bri';

    private const UPLOAD_URL = '/admin/monitoring/upload';

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'pumk_admin', 'is_active' => true, 'must_change_password' => false,
        ]), 'pumk');
    }

    public function test_admin_can_download_real_empty_template_without_changing_business_data(): void
    {
        $response = $this->get(self::DOWNLOAD_URL)->assertOk()->assertDownload('template-snapshot-pumk-bri.xlsx');
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $workbook = $this->xml($archive['xl/workbook.xml']->getContent());
        $names = [];
        foreach ($workbook->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) {
            $names[] = $sheet->getAttribute('name');
            $this->assertSame('', $sheet->getAttribute('state'));
        }
        $this->assertSame(['Petunjuk', 'Jan'], $names);

        $month = $this->xml($archive['xl/worksheets/sheet2.xml']->getContent());
        $xpath = $this->xpath($month);
        $this->assertSame('Total Saldo Piutang', $this->cellValue($xpath, 'G1'));
        $this->assertSame('SUM(H3:H1048576)', $xpath->query('//x:c[@r="H1"]/x:f')->item(0)?->textContent);
        $this->assertSame('0', $this->cellValue($xpath, 'H1'));
        $this->assertSame('n', $xpath->query('//x:c[@r="H1"]')->item(0)?->getAttribute('t'));
        $this->assertSame([
            'No', 'Nama Mitra Binaan', 'Alamat', 'Wilayah', 'Sektor Usaha',
            'Pinjaman', 'Tenor', 'Saldo Piutang', 'Kolektibilitas',
        ], array_map(fn (string $column): ?string => $this->cellValue($xpath, $column.'2'), range('A', 'I')));
        $this->assertNull($this->cellValue($xpath, 'B3'));
        $this->assertSame('2', $xpath->query('//x:pane')->item(0)?->getAttribute('ySplit'));
        $this->assertSame('auto', $this->xpath($workbook)->query('//x:calcPr')->item(0)?->getAttribute('calcMode'));

        $read = app(PumkBriWorkbookReader::class)->read($path);
        $this->assertSame('0', $read['Jan'][1]['H']);
        $this->assertSame('Saldo Piutang', $read['Jan'][2]['H']);
        $this->assertNull($read['Jan'][3]['B'] ?? null);
        $this->assertArrayHasKey('Petunjuk', $read);
        foreach (['pumk_bri_mitra', 'pumk_bri_fasilitas', 'pumk_bri_snapshot_bulanan', 'pumk_bri_rka_tahunan', 'pumk_bri_penyaluran_bulanan', 'pumk_mitra', 'pumk_pinjaman', 'pumk_activity_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_download_is_not_public_or_available_to_another_role(): void
    {
        auth('web')->logout();
        auth('pumk')->logout();
        $this->get(self::DOWNLOAD_URL)->assertRedirect();

        $this->actingAs(User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'must_change_password' => false,
        ]), 'pumk');
        $this->get(self::DOWNLOAD_URL)->assertRedirect();
    }

    public function test_button_only_appears_for_bri_selection_including_validation_retry(): void
    {
        $this->get(self::UPLOAD_URL)->assertOk()->assertSee('id="bri_template"', false)
            ->assertSee('id="bri_template" class="monitoring-template"  hidden', false);
        $this->followingRedirects()->post(self::UPLOAD_URL, ['import_type' => 'pumk_bri_snapshot'])
            ->assertOk()->assertSee('Download Template PUMK BRI');
        $this->followingRedirects()->post(self::UPLOAD_URL, ['import_type' => 'tjsl'])
            ->assertOk()->assertSee('id="bri_template" class="monitoring-template"  hidden', false);
    }

    public function test_empty_downloaded_template_fails_without_creating_snapshots(): void
    {
        $this->post(self::UPLOAD_URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2028',
            'monitoring_file' => $this->upload($this->asset()),
        ])->assertSessionHas('import_result.summary.Jan.status', 'failed')
            ->assertSessionHas('import_result.summary.Petunjuk.status', 'ignored');

        $this->assertDatabaseCount('pumk_bri_mitra', 0);
        $this->assertDatabaseCount('pumk_bri_fasilitas', 0);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 0);
    }

    public function test_filled_copy_imports_positive_and_zero_balances_and_skips_existing_period(): void
    {
        $copy = $this->filledCopy();
        $this->post(self::UPLOAD_URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2028',
            'monitoring_file' => $this->upload($copy),
        ])->assertSessionHas('import_result.summary.Jan.status', 'imported')
            ->assertSessionHas('import_result.summary.Jan.tahun', 2028)
            ->assertSessionHas('import_result.summary.Jan.baris', 2);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['tahun' => 2028, 'bulan' => 1, 'saldo_piutang' => 1200]);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['tahun' => 2028, 'bulan' => 1, 'saldo_piutang' => 0]);
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 2);

        $this->post(self::UPLOAD_URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2028',
            'monitoring_file' => $this->upload($copy),
        ])->assertSessionHas('import_result.summary.Jan.status', 'skipped');
        $this->assertDatabaseCount('pumk_bri_snapshot_bulanan', 2);
    }

    public function test_explicit_sheet_year_overrides_default_and_invalid_total_is_rejected(): void
    {
        $explicit = $this->filledCopy('Sep 2027');
        $this->post(self::UPLOAD_URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2028',
            'monitoring_file' => $this->upload($explicit),
        ])->assertSessionHas('import_result.summary.Sep 2027.status', 'imported')
            ->assertSessionHas('import_result.summary.Sep 2027.tahun', 2027);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', ['tahun' => 2027, 'bulan' => 9]);

        $bad = $this->filledCopy('Okt 2027', '999');
        $this->post(self::UPLOAD_URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2028',
            'monitoring_file' => $this->upload($bad),
        ])->assertSessionHas('import_result.summary.Okt 2027.status', 'failed');
        $this->assertDatabaseMissing('pumk_bri_snapshot_bulanan', ['tahun' => 2027, 'bulan' => 10]);

        $badHeader = $this->filledCopy('Nov 2027', '1200', true);
        $this->post(self::UPLOAD_URL, [
            'import_type' => 'pumk_bri_snapshot', 'default_year' => '2028',
            'monitoring_file' => $this->upload($badHeader),
        ])->assertSessionHasErrors('monitoring_file');
        $this->assertDatabaseMissing('pumk_bri_snapshot_bulanan', ['tahun' => 2027, 'bulan' => 11]);
    }

    private function asset(): string
    {
        return resource_path('templates/pumk-bri-snapshot.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('snapshot.xlsx', file_get_contents($path));
    }

    private function xml(string $contents): DOMDocument
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($contents, LIBXML_NONET));

        return $document;
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', self::NS);

        return $xpath;
    }

    private function cellValue(DOMXPath $xpath, string $address): ?string
    {
        return $xpath->query('//x:c[@r="'.$address.'"]/x:v')->item(0)?->textContent;
    }

    private function filledCopy(string $month = 'Jan', string $cachedTotal = '1200', bool $badHeader = false): string
    {
        $base = tempnam(sys_get_temp_dir(), 'pumk-bri-template-');
        $this->assertNotFalse($base);
        unlink($base);
        $zipPath = $base.'.zip';
        $xlsxPath = $base.'.xlsx';
        $this->assertTrue(copy($this->asset(), $zipPath));
        $archive = new PharData($zipPath);
        $monthXml = $this->xml($archive['xl/worksheets/sheet2.xml']->getContent());
        $xpath = $this->xpath($monthXml);
        $this->setCell($monthXml, $xpath, 'H1', $cachedTotal, true);
        if ($badHeader) {
            $this->setCell($monthXml, $xpath, 'I2', 'Status');
        }
        foreach ([3 => ['Mitra Uji A', '1200', 'L'], 4 => ['Mitra Uji B', '0', 'KL']] as $row => [$name, $balance, $quality]) {
            $this->setCell($monthXml, $xpath, 'B'.$row, $name);
            $this->setCell($monthXml, $xpath, 'D'.$row, 'Madiun');
            $this->setCell($monthXml, $xpath, 'E'.$row, 'Jasa');
            $this->setCell($monthXml, $xpath, 'F'.$row, '2000', true);
            $this->setCell($monthXml, $xpath, 'G'.$row, '12 M');
            $this->setCell($monthXml, $xpath, 'H'.$row, $balance, true);
            $this->setCell($monthXml, $xpath, 'I'.$row, $quality);
        }
        $archive->addFromString('xl/worksheets/sheet2.xml', $monthXml->saveXML());
        if ($month !== 'Jan') {
            $workbook = $this->xml($archive['xl/workbook.xml']->getContent());
            foreach ($workbook->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) {
                if ($sheet->getAttribute('name') === 'Jan') {
                    $sheet->setAttribute('name', $month);
                }
            }
            $archive->addFromString('xl/workbook.xml', $workbook->saveXML());
        }
        unset($archive);
        $this->assertTrue(rename($zipPath, $xlsxPath));
        $this->beforeApplicationDestroyed(static fn () => @unlink($xlsxPath));

        return $xlsxPath;
    }

    private function setCell(DOMDocument $document, DOMXPath $xpath, string $address, string $value, bool $numeric = false): void
    {
        $cell = $xpath->query('//x:c[@r="'.$address.'"]')->item(0);
        if (! $cell instanceof DOMElement) {
            preg_match('/\d+$/', $address, $matches);
            $row = $xpath->query('//x:row[@r="'.$matches[0].'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $row);
            $cell = $document->createElementNS(self::NS, 'x:c');
            $cell->setAttribute('r', $address);
            $row->appendChild($cell);
        }
        $cell->setAttribute('t', $numeric ? 'n' : 'str');
        $cached = $document->createElementNS(self::NS, 'x:v', $value);
        $old = $xpath->query('x:v', $cell)->item(0);
        if ($old !== null) {
            $cell->replaceChild($cached, $old);
        } else {
            $cell->appendChild($cached);
        }
    }
}
