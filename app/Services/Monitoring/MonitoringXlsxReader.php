<?php

namespace App\Services\Monitoring;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use XMLReader;

class MonitoringXlsxReader
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_ARCHIVE_ENTRIES = 3000;

    private const MAX_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    private const MAX_CELLS_PER_SHEET = 200_000;

    /**
     * @param  list<string>  $wantedSheets
     * @return array<string, array{headers:list<string>, rows:list<array{row:int, values:array<string, ?string>}>}>
     */
    public function read(string $path, array $wantedSheets): array
    {
        $this->assertReadableWorkbook($path);

        try {
            $archive = new PharData($path);
        } catch (\Throwable $exception) {
            throw new RuntimeException('File bukan workbook XLSX yang dapat dibaca.', 0, $exception);
        }

        $this->assertArchiveIsReasonable($archive);
        $workbook = $this->loadXml(
            $this->entryContents($archive, 'xl/workbook.xml'),
            'Metadata workbook tidak dapat dibaca.',
        );
        $relationships = $this->worksheetRelationships($archive);
        $sharedStrings = $this->readSharedStrings($archive);
        $wanted = array_fill_keys($wantedSheets, true);
        $xpath = new DOMXPath($workbook);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $result = [];

        foreach ($xpath->query('//m:sheets/m:sheet') ?: [] as $sheet) {
            if (! $sheet instanceof DOMElement) {
                continue;
            }

            $name = trim($sheet->getAttribute('name'));
            if (! isset($wanted[$name])) {
                continue;
            }

            $relationshipId = $sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            );
            $target = $relationships[$relationshipId] ?? null;
            if ($target === null) {
                throw new RuntimeException("Worksheet {$name} tidak dapat ditemukan di dalam workbook.");
            }

            $result[$name] = $this->readWorksheet(
                $this->entryContents($archive, $target),
                $sharedStrings,
            );
        }

        return $result;
    }

    private function assertReadableWorkbook(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File XLSX tidak ditemukan atau tidak dapat dibaca.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Ukuran file XLSX tidak valid atau melebihi 10 MB.');
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

    /** @return array<string, string> */
    private function worksheetRelationships(PharData $archive): array
    {
        $document = $this->loadXml(
            $this->entryContents($archive, 'xl/_rels/workbook.xml.rels'),
            'Relasi worksheet tidak dapat dibaca.',
        );
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationships = [];

        foreach ($xpath->query('//p:Relationship') ?: [] as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            $target = ltrim(str_replace('\\', '/', $relationship->getAttribute('Target')), '/');
            if (! str_starts_with($target, 'xl/')) {
                $target = 'xl/'.$target;
            }

            $relationships[$relationship->getAttribute('Id')] = $this->normalizeArchivePath($target);
        }

        return $relationships;
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
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $values[] = $this->textNodes($reader->readOuterXml());
            }
        }
        $reader->close();

        return $values;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array{headers:list<string>, rows:list<array{row:int, values:array<string, ?string>}>}
     */
    private function readWorksheet(string $xml, array $sharedStrings): array
    {
        $reader = new XMLReader;
        if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Worksheet XLSX tidak dapat dibaca.');
        }

        $cellsByRow = [];
        $cellCount = 0;
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            if (++$cellCount > self::MAX_CELLS_PER_SHEET) {
                throw new RuntimeException('Worksheet melebihi batas 200.000 sel.');
            }

            $reference = strtoupper((string) $reader->getAttribute('r'));
            if (! preg_match('/^([A-Z]+)(\d+)$/', $reference, $matches)) {
                continue;
            }

            $type = (string) $reader->getAttribute('t');
            $cellsByRow[(int) $matches[2]][$matches[1]] = $this->cellValue(
                $reader->readOuterXml(),
                $type,
                $sharedStrings,
            );
        }
        $reader->close();

        $headerCells = $cellsByRow[1] ?? [];
        ksort($headerCells);
        $headersByColumn = [];
        foreach ($headerCells as $column => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized !== '') {
                $headersByColumn[$column] = $normalized;
            }
        }

        if (count(array_unique($headersByColumn)) !== count($headersByColumn)) {
            throw new RuntimeException('Worksheet memiliki nama header yang duplikat.');
        }

        $rows = [];
        foreach ($cellsByRow as $rowNumber => $cells) {
            if ($rowNumber === 1) {
                continue;
            }

            $values = [];
            foreach ($headersByColumn as $column => $header) {
                $values[$header] = $cells[$column] ?? null;
            }

            $hasValue = false;
            foreach ($values as $value) {
                if (trim((string) $value) !== '') {
                    $hasValue = true;
                    break;
                }
            }

            if ($hasValue) {
                $rows[] = ['row' => $rowNumber, 'values' => $values];
            }
        }

        return ['headers' => array_values($headersByColumn), 'rows' => $rows];
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(string $cellXml, string $type, array $sharedStrings): ?string
    {
        if ($type === 'inlineStr') {
            $value = $this->textNodes($cellXml);

            return $value === '' ? null : $value;
        }

        $document = $this->loadXml($cellXml, 'Sel worksheet tidak dapat dibaca.');
        $valueNode = $document->getElementsByTagName('v')->item(0);
        if ($valueNode === null) {
            return null;
        }

        $raw = $valueNode->textContent;
        if ($type === 's') {
            return $sharedStrings[(int) $raw] ?? null;
        }

        if ($type === 'b') {
            return $raw === '1' ? '1' : '0';
        }

        return $raw === '' ? null : $raw;
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

    private function normalizeHeader(mixed $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value)) ?? '';

        return strtolower(preg_replace('/\s+/u', '_', $value) ?? $value);
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
            } else {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }
}
