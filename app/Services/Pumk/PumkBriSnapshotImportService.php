<?php

namespace App\Services\Pumk;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PumkBriSnapshotImportService
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

    public function __construct(
        private readonly PumkBriWorkbookReader $reader,
        private readonly PumkBriIdentityMatcher $matcher,
        private readonly PumkBriFacilityStatusService $facilityStatus,
    ) {}

    /**
     * @return array<string, array{status:string,bulan:?int,tahun:?int,baris:int,total_saldo:string,pesan:string}>
     */
    public function import(string $path, int $defaultYear, bool $force = false): array
    {
        if ($defaultYear < 1900 || $defaultYear > 2100) {
            throw new InvalidArgumentException('Tahun default harus berada pada rentang 1900-2100.');
        }

        $sheets = $this->reader->read($path);
        $summary = [];

        foreach ($sheets as $sheetName => $rows) {
            $period = $this->periodFromSheetName($sheetName, $defaultYear);
            if ($period === null) {
                $summary[$sheetName] = $this->result('ignored', null, null, 0, '0.00', 'Dilewati - nama sheet bukan nama bulan.');

                continue;
            }

            [$month, $year] = $period;
            if (! $force && PumkBriSnapshotBulanan::query()->where('bulan', $month)->where('tahun', $year)->exists()) {
                $summary[$sheetName] = $this->result(
                    'skipped',
                    $month,
                    $year,
                    0,
                    '0.00',
                    'Dilewati - periode sudah pernah diimpor. Gunakan --force untuk menggantinya.',
                );

                continue;
            }

            try {
                $parsed = $this->parseSheet($sheetName, $rows);
                $this->persistPeriod($sheetName, $month, $year, $parsed['records'], $force);
                $summary[$sheetName] = $this->result(
                    'imported',
                    $month,
                    $year,
                    count($parsed['records']),
                    $parsed['total'],
                    'Berhasil diimpor.',
                );
            } catch (PumkBriIdentityNeedsReview $exception) {
                foreach ($exception->cases as $case) {
                    PumkBriIdentityReview::query()->updateOrCreate(
                        [
                            'tahun' => $year,
                            'bulan' => $month,
                            'source_sheet' => $sheetName,
                            'source_row' => $case['source_row'],
                        ],
                        [
                            'source_profile' => $case['profile'],
                            'source_fingerprint' => $this->profileFingerprint($case['profile']),
                            'candidate_fasilitas_ids' => $case['candidates'],
                            'reason' => $case['reason'],
                            'status' => 'pending',
                            'resolved_fasilitas_id' => null,
                            'resolved_mitra_id' => null,
                            'resolution_action' => null,
                        ],
                    );
                }
                $summary[$sheetName] = $this->result(
                    'needs_review', $month, $year, 0, '0.00',
                    $exception->getMessage().' Baris: '.implode(', ', array_column($exception->cases, 'source_row')).'.',
                );
            } catch (Throwable $exception) {
                $summary[$sheetName] = $this->result(
                    'failed',
                    $month,
                    $year,
                    0,
                    '0.00',
                    $exception->getMessage(),
                );
            }
        }

        $this->facilityStatus->sync();

        return $summary;
    }

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{records:list<array<string, mixed>>,total:string}
     */
    private function parseSheet(string $sheetName, array $rows): array
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
    private function findHeader(string $sheetName, array $rows): array
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

    /** @param list<array<string, mixed>> $records */
    private function persistPeriod(
        string $sheetName,
        int $month,
        int $year,
        array $records,
        bool $replacePeriod,
    ): void {
        DB::transaction(function () use ($sheetName, $month, $year, $records, $replacePeriod): void {
            $facilities = PumkBriFasilitas::query()->with('snapshots')->get();
            $planned = [];
            $newProfiles = [];
            $reviews = [];
            $usedFacilities = [];
            $decisions = PumkBriIdentityReview::query()
                ->where('tahun', $year)->where('bulan', $month)->where('source_sheet', $sheetName)
                ->where('status', 'resolved')->get()->keyBy('source_row');

            foreach ($records as $record) {
                $resolution = $decisions->get($record['source_row']);
                if ($resolution !== null && ! hash_equals(
                    $this->profileFingerprint($resolution->source_profile ?? []),
                    $this->profileFingerprint($this->sourceProfile($record)),
                )) {
                    $resolution = null;
                }
                $decision = $resolution === null
                    ? $this->matcher->decide($record, $facilities)
                    : [
                        'action' => $resolution->resolution_action,
                        'facility' => $facilities->firstWhere('id', $resolution->resolved_fasilitas_id),
                        'mitra' => $resolution->resolved_mitra_id === null
                            ? null
                            : PumkBriMitra::query()->find($resolution->resolved_mitra_id),
                        'candidates' => [],
                        'reason' => null,
                    ];
                if ($decision['action'] === 'match' && $decision['facility'] === null) {
                    $decision = ['action' => 'review', 'facility' => null, 'candidates' => [], 'reason' => 'Fasilitas hasil validasi tidak lagi tersedia.'];
                }
                foreach ($resolution === null ? $newProfiles : [] as $other) {
                    $comparison = $this->matcher->compareSourceProfiles($record, $other);
                    if ($comparison !== 'unrelated') {
                        $decision = [
                            'action' => 'review',
                            'facility' => null,
                            'candidates' => [],
                            'reason' => $comparison === 'match'
                                ? 'Profil duplikat dalam satu periode.'
                                : 'Profil mirip dengan baris lain pada periode ini.',
                        ];
                        break;
                    }
                }

                if ($decision['action'] === 'match' && isset($usedFacilities[$decision['facility']->id])) {
                    $decision = [
                        'action' => 'review', 'facility' => null,
                        'candidates' => [$decision['facility']->id],
                        'reason' => 'Dua baris mengarah ke fasilitas yang sama dalam satu periode.',
                    ];
                }

                if ($decision['action'] === 'review') {
                    $reviews[] = [
                        'source_row' => $record['source_row'],
                        'profile' => $this->sourceProfile($record),
                        'candidates' => $decision['candidates'],
                        'reason' => $decision['reason'],
                    ];
                } else {
                    $planned[] = [
                        'record' => $record,
                        'facility' => $decision['facility'],
                        'mitra' => $decision['mitra'] ?? null,
                        'resolution' => $resolution,
                    ];
                    if ($decision['facility'] !== null) {
                        $usedFacilities[$decision['facility']->id] = true;
                    } else {
                        $newProfiles[] = $record;
                    }
                }
            }

            if ($reviews !== []) {
                throw new PumkBriIdentityNeedsReview($reviews);
            }

            if ($replacePeriod) {
                PumkBriSnapshotBulanan::query()->where('bulan', $month)->where('tahun', $year)->delete();
            }

            foreach ($planned as $item) {
                $record = $item['record'];
                $facility = $item['facility'];
                if ($facility === null) {
                    $mitra = $item['resolution']?->resolved_mitra_id !== null
                        ? PumkBriMitra::query()->findOrFail($item['resolution']->resolved_mitra_id)
                        : ($item['mitra'] ?? null);
                    $mitra ??= PumkBriMitra::query()->create([
                        'source_key' => bin2hex(random_bytes(32)),
                        'nama_mitra' => $record['nama_mitra'],
                        'alamat' => $record['alamat'],
                        'wilayah' => $record['wilayah'],
                        'sektor_usaha' => $record['sektor_usaha'],
                        // Retain the first verified profile in legacy columns for
                        // backwards-compatible screens/manual enrichment. The
                        // authoritative month-by-month values remain on snapshots.
                        'pinjaman' => $record['pinjaman'],
                        'tenor_raw' => $record['tenor_raw'],
                    ]);
                    $facility = PumkBriFasilitas::query()->create([
                        'mitra_id' => $mitra->id,
                        'reference_key' => (string) Str::uuid(),
                        'pinjaman' => $record['pinjaman'],
                        'tenor_raw' => $record['tenor_raw'],
                        'sektor_usaha' => $record['sektor_usaha'],
                    ]);
                    if ($item['resolution'] !== null) {
                        $item['resolution']->update([
                            'resolution_action' => 'match',
                            'resolved_fasilitas_id' => $facility->id,
                            'resolved_mitra_id' => $facility->mitra_id,
                        ]);
                    }
                }

                // Profil fasilitas merupakan source-owned field. Nilai terbaru,
                // termasuk null, mengikuti workbook; field manual tidak disentuh.
                $facility->forceFill([
                    'pinjaman' => $record['pinjaman'],
                    'tenor_raw' => $record['tenor_raw'],
                    'sektor_usaha' => $record['sektor_usaha'],
                ])->save();

                PumkBriSnapshotBulanan::query()->updateOrCreate(
                    ['fasilitas_id' => $facility->id, 'bulan' => $month, 'tahun' => $year],
                    [
                        'mitra_id' => $facility->mitra_id,
                        'saldo_piutang' => $record['saldo_piutang'],
                        'kolektibilitas_kode' => $record['kolektibilitas_kode'],
                        'kolektibilitas_label' => $record['kolektibilitas_label'],
                        'source_sheet' => $sheetName,
                        'source_row' => $record['source_row'],
                        'no_urut_sumber' => $record['no_urut_sumber'],
                        'nama_mitra_sumber' => $record['nama_mitra'],
                        'alamat_sumber' => $record['alamat'],
                        'wilayah_sumber' => $record['wilayah'],
                        'sektor_usaha_sumber' => $record['sektor_usaha'],
                        'pinjaman_sumber' => $record['pinjaman'],
                        'tenor_sumber' => $record['tenor_raw'],
                        'tanggal_pencairan_sumber' => null,
                        'tanggal_jatuh_tempo_sumber' => null,
                        'profil_sumber_terverifikasi' => true,
                        'source_payload' => $record['source_payload'],
                    ],
                );
            }
        });
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function sourceProfile(array $record): array
    {
        return array_intersect_key($record, array_flip([
            'nama_mitra', 'alamat', 'wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw',
        ]));
    }

    /** @param array<string, mixed> $profile */
    private function profileFingerprint(array $profile): string
    {
        $profile = array_intersect_key($profile, array_flip([
            'nama_mitra', 'wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw',
        ]));
        ksort($profile);

        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @return array{int,int}|null */
    private function periodFromSheetName(string $sheetName, int $defaultYear): ?array
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

    /**
     * @return array{status:string,bulan:?int,tahun:?int,baris:int,total_saldo:string,pesan:string}
     */
    private function result(
        string $status,
        ?int $month,
        ?int $year,
        int $rows,
        string $total,
        string $message,
    ): array {
        return [
            'status' => $status,
            'bulan' => $month,
            'tahun' => $year,
            'baris' => $rows,
            'total_saldo' => $total,
            'pesan' => $message,
        ];
    }
}
