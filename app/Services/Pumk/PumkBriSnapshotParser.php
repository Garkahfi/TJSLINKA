<?php

namespace App\Services\Pumk;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PumkBriSnapshotParser
{
    private const EXPECTED_HEADERS = [
        'no',
        'nama_mitra_binaan',
        'alamat',
        'wilayah',
        'sektor_usaha',
        'pinjaman',
        'tenor',
        'saldo_piutang',
        'kolektibilitas',
    ];

    private const MONTHS = [
        'jan' => 1, 'januari' => 1,
        'feb' => 2, 'februari' => 2,
        'mar' => 3, 'maret' => 3,
        'apr' => 4, 'april' => 4,
        'mei' => 5,
        'jun' => 6, 'juni' => 6,
        'jul' => 7, 'juli' => 7,
        'ags' => 8, 'agst' => 8, 'agu' => 8, 'agustus' => 8,
        'sep' => 9, 'september' => 9,
        'okt' => 10, 'oktober' => 10,
        'nov' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12,
    ];

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{records:list<array<string, mixed>>,total:string}
     */
    public function parseSheet(string $sheetName, array $rows): array
    {
        [$headerRow, $columns] = $this->findHeader($sheetName, $rows);
        $summaryRaw = $rows[$headerRow - 1][$columns['saldo_piutang']] ?? null;
        $summaryTotal = $this->decimal($summaryRaw, "ringkasan Saldo Piutang sheet {$sheetName}");
        $records = [];
        $calculatedTotal = '0.00';

        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            $rawName = $cells[$columns['nama_mitra_binaan']] ?? null;
            if (trim((string) $rawName) === '') {
                continue;
            }

            $name = $this->partnerName($rawName);
            $balance = $this->decimal($cells[$columns['saldo_piutang']] ?? null, 'saldo piutang', $rowNumber);
            [$qualityCode, $qualityLabel] = $this->collectibility(
                $cells[$columns['kolektibilitas']] ?? null,
                $rowNumber,
            );

            $records[] = [
                'nama_mitra' => $name,
                'alamat' => $this->nullableLongText($cells[$columns['alamat']] ?? null),
                'wilayah' => $this->nullableText($cells[$columns['wilayah']] ?? null),
                'sektor_usaha' => $this->nullableText($cells[$columns['sektor_usaha']] ?? null),
                'pinjaman' => $this->nullableDecimal($cells[$columns['pinjaman']] ?? null, 'pinjaman', $rowNumber),
                'tenor_raw' => $this->nullableText($cells[$columns['tenor']] ?? null),
                'saldo_piutang' => $balance,
                'kolektibilitas_kode' => $qualityCode,
                'kolektibilitas_label' => $qualityLabel,
                'source_row' => $rowNumber,
                'no_urut_sumber' => $this->nullableInteger($cells[$columns['no']] ?? null, 'no', $rowNumber),
                'source_payload' => array_map(static fn (string $column): mixed => $cells[$column] ?? null, $columns),
            ];
            $calculatedTotal = bcadd($calculatedTotal, $balance, 2);
        }

        if ($records === []) {
            throw new InvalidArgumentException("Sheet {$sheetName} tidak memiliki baris data.");
        }
        if (bccomp($summaryTotal, $calculatedTotal, 2) !== 0) {
            throw new InvalidArgumentException(
                "Total Saldo Piutang sheet {$sheetName} tidak cocok: ringkasan {$summaryTotal}, hasil baris {$calculatedTotal}.",
            );
        }

        return ['records' => $records, 'total' => $calculatedTotal];
    }

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{int,array<string,string>}
     */
    public function findHeader(string $sheetName, array $rows): array
    {
        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber > 20) {
                break;
            }

            $columns = [];
            foreach ($cells as $column => $value) {
                $header = $this->normalizeHeader($value);
                if ($header !== '') {
                    $columns[$header] = $column;
                }
            }

            if (array_diff(self::EXPECTED_HEADERS, array_keys($columns)) === []) {
                return [$rowNumber, $columns];
            }
        }

        throw new InvalidArgumentException("Header wajib tidak ditemukan pada 20 baris pertama sheet {$sheetName}.");
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    public function sourceProfile(array $record): array
    {
        return array_intersect_key($record, array_flip([
            'nama_mitra', 'alamat', 'wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw',
        ]));
    }

    /** @param array<string, mixed> $profile */
    public function profileFingerprint(array $profile): string
    {
        $profile = array_intersect_key($profile, array_flip([
            'nama_mitra', 'wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw',
        ]));
        ksort($profile);

        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @return array{int,int}|null */
    public function periodFromSheetName(string $sheetName, int $defaultYear): ?array
    {
        $normalized = Str::of($sheetName)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $month = null;
        foreach ($tokens as $token) {
            if (isset(self::MONTHS[$token])) {
                $month = self::MONTHS[$token];
                break;
            }
        }
        if ($month === null) {
            return null;
        }

        preg_match('/\b(20\d{2})\b/', $normalized, $yearMatch);
        $year = isset($yearMatch[1]) ? (int) $yearMatch[1] : $defaultYear;

        return [$month, $year];
    }

    private function partnerName(mixed $value): string
    {
        $name = trim((string) $value);
        if ($name === '') {
            throw new InvalidArgumentException('Nama Mitra Binaan wajib diisi.');
        }

        return mb_substr($name, 0, 255);
    }

    private function normalizeHeader(mixed $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function nullableLongText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimal(mixed $value, string $field, ?int $row = null): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            throw new InvalidArgumentException($this->fieldError($field, $row, 'wajib berupa angka.'));
        }
        $normalized = str_ireplace(['rp', ' ', "\u{00A0}"], '', $normalized);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $parts = explode(',', $normalized);
            $normalized = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0].'.'.$parts[1]
                : implode('', $parts);
        }

        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException($this->fieldError($field, $row, 'harus berupa angka nol atau positif.'));
        }
        [$integer] = explode('.', $normalized, 2);
        if (strlen(ltrim($integer, '0') ?: '0') > 16) {
            throw new InvalidArgumentException($this->fieldError($field, $row, 'melebihi batas database.'));
        }

        return bcadd($normalized, '0', 2);
    }

    private function nullableDecimal(mixed $value, string $field, int $row): ?string
    {
        return trim((string) $value) === '' ? null : $this->decimal($value, $field, $row);
    }

    private function nullableInteger(mixed $value, string $field, int $row): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException($this->fieldError($field, $row, 'harus berupa bilangan bulat.'));
        }

        return (int) $value;
    }

    /** @return array{string,string} */
    private function collectibility(mixed $value, int $row): array
    {
        $normalized = Str::of((string) $value)->trim()->upper()->replaceMatches('/[^A-Z]+/', ' ')->trim()->toString();

        return match (true) {
            $normalized === 'L', str_starts_with($normalized, 'LANCAR') => ['L', 'Lancar'],
            $normalized === 'KL', str_starts_with($normalized, 'KURANG LANCAR') => ['KL', 'Kurang Lancar'],
            $normalized === 'D', str_starts_with($normalized, 'DIRAGUKAN') => ['D', 'Diragukan'],
            $normalized === 'M', str_starts_with($normalized, 'MACET') => ['M', 'Macet'],
            default => throw new InvalidArgumentException(
                $this->fieldError('kolektibilitas', $row, "tidak dikenali: {$value}."),
            ),
        };
    }

    private function fieldError(string $field, ?int $row, string $message): string
    {
        return ($row === null ? '' : "Baris {$row}: ")."{$field} {$message}";
    }
}
