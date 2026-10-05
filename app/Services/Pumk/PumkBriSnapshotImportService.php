<?php

namespace App\Services\Pumk;

use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PumkBriSnapshotImportService
{
    public function __construct(
        private readonly PumkBriWorkbookReader $reader,
        private readonly PumkBriSnapshotParser $parser,
        private readonly PumkBriSnapshotPeriodWriter $writer,
        private readonly PumkBriFacilityStatusService $facilityStatus,
    ) {}

    /**
     * @return array<string, array{status:string,bulan:?int,tahun:?int,baris:int,total_saldo:string,pesan:string}>
     */
    public function import(string $path, int $defaultYear, bool $force = false): array
    {
        $this->validateYear($defaultYear);
        $sheets = $this->reader->read($path);

        return $this->importSheets($sheets, $defaultYear, $force, false);
    }

    /**
     * Web uploads never replace a period and reject an incompatible workbook before any writes.
     *
     * @return array<string, array{status:string,bulan:?int,tahun:?int,baris:int,total_saldo:string,pesan:string}>
     */
    public function importForWebUpload(string $path, int $defaultYear): array
    {
        $this->validateYear($defaultYear);
        $sheets = $this->reader->read($path);
        $compatible = false;
        foreach ($sheets as $sheetName => $rows) {
            if ($this->parser->periodFromSheetName($sheetName, $defaultYear) === null) {
                continue;
            }
            try {
                $this->parser->findHeader($sheetName, $rows);
                $compatible = true;
                break;
            } catch (InvalidArgumentException) {
                // Another monthly sheet may still be compatible; no business data is written here.
            }
        }
        if (! $compatible) {
            throw new InvalidArgumentException('Workbook tidak memiliki sheet Snapshot PUMK BRI dengan header yang sesuai.');
        }

        return $this->importSheets($sheets, $defaultYear, false, true);
    }

    private function validateYear(int $defaultYear): void
    {
        if ($defaultYear < 1900 || $defaultYear > 2100) {
            throw new InvalidArgumentException('Tahun default harus berada pada rentang 1900-2100.');
        }
    }

    /**
     * @param  array<string, array<int, array<string, ?string>>>  $sheets
     * @return array<string, array{status:string,bulan:?int,tahun:?int,baris:int,total_saldo:string,pesan:string}>
     */
    private function importSheets(array $sheets, int $defaultYear, bool $force, bool $webUpload): array
    {
        $summary = [];

        foreach ($sheets as $sheetName => $rows) {
            $period = $this->parser->periodFromSheetName($sheetName, $defaultYear);
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
                    $webUpload
                        ? 'Periode ini sudah pernah diimpor; data lama tetap digunakan.'
                        : 'Dilewati - periode sudah pernah diimpor. Gunakan --force untuk menggantinya.',
                );

                continue;
            }

            try {
                $parsed = $this->parser->parseSheet($sheetName, $rows);
                $this->writer->persistPeriod($sheetName, $month, $year, $parsed['records'], $force);
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
                            'source_fingerprint' => $this->parser->profileFingerprint($case['profile']),
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
                if ($webUpload && ! $exception instanceof InvalidArgumentException) {
                    Log::error('Import snapshot PUMK BRI gagal.', ['exception_type' => $exception::class]);
                }
                $summary[$sheetName] = $this->result(
                    'failed',
                    $month,
                    $year,
                    0,
                    '0.00',
                    $webUpload && ! $exception instanceof InvalidArgumentException
                        ? 'Periode gagal diproses. Periksa log aplikasi.'
                        : $exception->getMessage(),
                );
            }
        }

        if (! $webUpload || collect($summary)->contains(fn (array $result): bool => $result['status'] === 'imported')) {
            $this->facilityStatus->sync();
        }

        return $summary;
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
