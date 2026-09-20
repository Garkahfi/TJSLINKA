<?php

namespace App\Services\Pumk;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use XMLReader;

/**
 * Pembaca OOXML kecil untuk workbook sumber PUMK yang formatnya sudah tetap.
 *
 * Nilai sel dikembalikan sebagai string mentah. Konversi tanggal dan angka
 * dilakukan oleh importer supaya identifier seperti KTP/telepon/rekening tidak
 * kehilangan nol di depan akibat konversi float yang terlalu dini.
 */
final class PumkXlsxReader
{
    public const DATA_START_ROW = 9;

    public const DATA_END_ROW = 402;

    private const MAX_FILE_BYTES = 50 * 1024 * 1024;

    private const MAX_ARCHIVE_ENTRIES = 5000;

    private const MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024;

    /**
     * @return array{
     *     sheet_name: string,
     *     tanggal_acuan_raw: ?string,
     *     date_1904: bool,
     *     rows: list<array{worksheet_row: int, cells: array<string, ?string>, source_warnings: list<string>}>
     * }
     */
    public function read(string $path): array
    {
        $this->assertReadableWorkbook($path);

        try {
            $archive = new PharData($path);
        } catch (\Throwable $exception) {
            throw new RuntimeException('File bukan workbook XLSX yang dapat dibaca.', 0, $exception);
        }

        $this->assertArchiveIsReasonable($archive);

        $workbookXml = $this->entryContents($archive, 'xl/workbook.xml');
        [$sheetName, $sheetPath] = $this->targetWorksheet($archive, $workbookXml);
        $sharedStrings = $this->readSharedStrings($archive);
        $worksheet = $this->readWorksheet($this->entryContents($archive, $sheetPath), $sharedStrings);
        $cellsByRow = $worksheet['cells'];

        $this->assertExpectedWorkbookLayout($sheetName, $cellsByRow);

        $rows = [];
        for ($worksheetRow = self::DATA_START_ROW; $worksheetRow <= self::DATA_END_ROW; $worksheetRow++) {
            $cells = $cellsByRow[$worksheetRow] ?? [];

            if ($this->blank($cells['A'] ?? null) && $this->blank($cells['B'] ?? null)) {
                continue;
            }

            $rows[] = [
                'worksheet_row' => $worksheetRow,
                'cells' => $cells,
                'source_warnings' => $worksheet['warnings'][$worksheetRow] ?? [],
            ];
        }

        return [
            'sheet_name' => $sheetName,
            'tanggal_acuan_raw' => $cellsByRow[5]['C'] ?? null,
            'date_1904' => $this->uses1904DateSystem($workbookXml),
            'rows' => $rows,
        ];
    }

    private function assertReadableWorkbook(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File XLSX tidak ditemukan atau tidak dapat dibaca.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Ukuran file XLSX tidak valid atau melebihi 50 MB.');
        }
    }

    private function assertArchiveIsReasonable(PharData $archive): void
    {
        $entries = 0;
        $uncompressedBytes = 0;

        foreach (new RecursiveIteratorIterator($archive) as $file) {
            $entries++;
            $uncompressedBytes += max(0, (int) $file->getSize());

            if ($entries > self::MAX_ARCHIVE_ENTRIES || $uncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('Struktur XLSX terlalu besar untuk diproses dengan aman.');
            }
        }
    }

    /** @return list<string> */
    private function readSharedStrings(PharData $archive): array
    {
        if (! isset($archive['xl/sharedStrings.xml'])) {
            return [];
        }

        $reader = new XMLReader;
        if (! $reader->XML($this->entryContents($archive, 'xl/sharedStrings.xml'), null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Shared strings XLSX tidak dapat dibaca.');
        }

        $values = [];
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }

            $values[] = $this->textNodes($reader->readOuterXml());
        }

        $reader->close();

        return $values;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array{cells: array<int, array<string, ?string>>, warnings: array<int, list<string>>}
     */
    private function readWorksheet(string $xml, array $sharedStrings): array
    {
        $reader = new XMLReader;
        if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Worksheet XLSX tidak dapat dibaca.');
        }

        $cellsByRow = [];
        $warningsByRow = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $reference = strtoupper((string) $reader->getAttribute('r'));
            if (! preg_match('/^([A-Z]+)(\d+)$/', $reference, $matches)) {
                continue;
            }

            $column = $matches[1];
            $row = (int) $matches[2];

            if ($row > self::DATA_END_ROW || $this->columnNumber($column) > $this->columnNumber('CK')) {
                continue;
            }

            $cellXml = $reader->readOuterXml();
            $cellsByRow[$row][$column] = $this->cellValue(
                $cellXml,
                (string) $reader->getAttribute('t'),
                $sharedStrings,
            );

            if ($this->formulaHasNoCachedValue($cellXml)) {
                $warningsByRow[$row][] = "formula_cached_value_missing_{$column}";
            }
        }

        $reader->close();

        return ['cells' => $cellsByRow, 'warnings' => $warningsByRow];
    }

    private function formulaHasNoCachedValue(string $cellXml): bool
    {
        $document = $this->loadXml($cellXml, 'Formula worksheet tidak dapat dibaca.');
        $values = $document->getElementsByTagName('v');

        return $document->getElementsByTagName('f')->length > 0
            && ($values->length === 0 || trim((string) $values->item(0)?->textContent) === '');
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(string $cellXml, string $type, array $sharedStrings): ?string
    {
        if ($type === 'inlineStr') {
            $value = $this->textNodes($cellXml);

            return $value === '' ? null : $value;
        }

        $document = $this->loadXml($cellXml, 'Sel worksheet tidak dapat dibaca.');
        $values = $document->getElementsByTagName('v');

        if ($values->length === 0) {
            return null;
        }

        $raw = $values->item(0)?->textContent ?? '';

        if ($type === 's') {
            return $sharedStrings[(int) $raw] ?? null;
        }

        if ($type === 'b') {
            return $raw === '1' ? '1' : '0';
        }

        return $raw === '' ? null : $raw;
    }

    /** @return array{0:string, 1:string} */
    private function targetWorksheet(PharData $archive, string $workbookXml): array
    {
        $workbook = $this->loadXml($workbookXml, 'Metadata workbook tidak dapat dibaca.');
        $xpath = new DOMXPath($workbook);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheet = null;
        foreach ($xpath->query('//m:sheets/m:sheet') ?: [] as $candidate) {
            if ($candidate instanceof DOMElement
                && trim($candidate->getAttribute('name')) === 'Database new versi baseon SPJ') {
                $sheet = $candidate;
                break;
            }
        }

        if (! $sheet instanceof DOMElement) {
            throw new RuntimeException('Worksheet sumber "Database new versi baseon SPJ" tidak ditemukan.');
        }

        $relationshipId = $sheet->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id',
        );

        $relationships = $this->loadXml(
            $this->entryContents($archive, 'xl/_rels/workbook.xml.rels'),
            'Relasi worksheet tidak dapat dibaca.',
        );
        $relationshipsXpath = new DOMXPath($relationships);
        $relationshipsXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($relationshipsXpath->query('//p:Relationship') ?: [] as $relationship) {
            if ($relationship instanceof DOMElement && $relationship->getAttribute('Id') === $relationshipId) {
                return [
                    trim($sheet->getAttribute('name')),
                    $this->normalizeArchivePath('xl/'.$relationship->getAttribute('Target')),
                ];
            }
        }

        throw new RuntimeException('Relasi worksheet sumber tidak dapat ditemukan.');
    }

    /** @param array<int, array<string, ?string>> $cellsByRow */
    private function assertExpectedWorkbookLayout(string $sheetName, array $cellsByRow): void
    {
        if ($sheetName !== 'Database new versi baseon SPJ') {
            throw new RuntimeException('Worksheet sumber harus bernama "Database new versi baseon SPJ".');
        }

        $expectedHeaders = [
            'A' => 'No',
            'B' => 'Nama Mitra Binaan',
            'C' => 'SPJ awal',
            'D' => 'Kontrak Reschedulling Ke-1',
            'G' => 'Kontrak Rescheduling Ke-4',
            'H' => 'Jenis Usaha',
            'P' => 'Jenis jaminan',
            'Z' => 'BERKAS SPJ',
            'AA' => 'BERKAS JAMINAN',
            'AB' => 'Tanggal Pencairan',
            'AD' => 'Pinjaman Pokok',
            'AT' => 'KOLEKTIBILITAS',
            'AX' => 'Angs Pokok',
            'BB' => 'Pokok',
            'CK' => 'Total Angs',
        ];

        foreach ($expectedHeaders as $column => $expected) {
            $actual = preg_replace('/\s+/u', ' ', trim((string) ($cellsByRow[8][$column] ?? '')));
            if (strtolower((string) $actual) !== strtolower($expected)) {
                throw new RuntimeException("Header XLSX pada kolom {$column} tidak sesuai format sumber PUMK.");
            }
        }
    }

    private function uses1904DateSystem(string $workbookXml): bool
    {
        $document = $this->loadXml($workbookXml, 'Metadata workbook tidak dapat dibaca.');
        $properties = $document->getElementsByTagName('workbookPr')->item(0);

        if (! $properties instanceof DOMElement) {
            return false;
        }

        return in_array(strtolower($properties->getAttribute('date1904')), ['1', 'true'], true);
    }

    private function entryContents(PharData $archive, string $path): string
    {
        if (! isset($archive[$path])) {
            throw new RuntimeException("Komponen XLSX wajib tidak ditemukan: {$path}");
        }

        $contents = $archive[$path]->getContent();
        if (! is_string($contents)) {
            throw new RuntimeException("Komponen XLSX tidak dapat dibaca: {$path}");
        }

        return $contents;
    }

    private function textNodes(string $xml): string
    {
        $document = $this->loadXml($xml, 'Teks XLSX tidak dapat dibaca.');
        $text = '';

        foreach ($document->getElementsByTagName('t') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    private function loadXml(string $xml, string $error): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException($error);
        }

        return $document;
    }

    private function normalizeArchivePath(string $path): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', ltrim($path, '/'))) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function columnNumber(string $column): int
    {
        $number = 0;

        foreach (str_split($column) as $letter) {
            $number = ($number * 26) + (ord($letter) - 64);
        }

        return $number;
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
