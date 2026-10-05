<?php

namespace App\Services\Pumk;

use App\Models\PumkImportBatch;
use App\Models\PumkImportRow;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PumkImportService
{
    public function __construct(
        private readonly PumkXlsxReader $reader,
        private readonly PumkImportValueParser $parser,
        private readonly PumkImportRowWriter $writer,
    ) {}

    /** @return array<string, int> */
    public function import(string $path, ?User $importer = null): array
    {
        if ($importer !== null && $importer->role !== 'super_admin') {
            throw new RuntimeException('Pengimpor harus memiliki role Super Admin.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File XLSX tidak ditemukan atau tidak dapat dibaca.');
        }

        $fileHash = hash_file('sha256', $path);
        if (! is_string($fileHash)) {
            throw new RuntimeException('Hash file XLSX tidak dapat dihitung.');
        }

        $batch = PumkImportBatch::where('file_hash', $fileHash)->first();

        $retryableBatch = $batch !== null && (
            $batch->status === 'failed'
            || ($batch->status === 'completed_with_warnings' && $batch->gagal > 0)
        );

        if ($batch !== null && ! $retryableBatch) {
            throw new DuplicatePumkImportException('File ini sudah pernah diimpor, tidak ada perubahan.');
        }

        if ($batch === null) {
            $batch = PumkImportBatch::create([
                'nama_file' => basename($path),
                'file_hash' => $fileHash,
                'status' => 'processing',
                'imported_by' => $importer?->id,
                'started_at' => now(),
            ]);
        } else {
            // Batch yang benar-benar gagal boleh dicoba ulang. Bersihkan data
            // anak dari percobaan parsial, tetapi pertahankan batch yang sama
            // agar constraint file_hash tetap menjadi pelindung idempotensi.
            DB::transaction(function () use ($batch, $path, $importer): void {
                $batch->rows()->delete();
                $batch->angsuran()->delete();
                $batch->saldoAwal()->delete();
                $batch->update([
                    'nama_file' => basename($path),
                    'status' => 'processing',
                    'tanggal_acuan' => null,
                    'total_baris' => 0,
                    'berhasil' => 0,
                    'gagal' => 0,
                    'imported_by' => $importer?->id,
                    'started_at' => now(),
                    'completed_at' => null,
                    'error_summary' => null,
                ]);
            });
        }

        try {
            $workbook = $this->reader->read($path);
            $tanggalAcuan = $this->parser->excelDate(
                $workbook['tanggal_acuan_raw'],
                $workbook['date_1904'],
            );

            if ($tanggalAcuan === null) {
                throw new RuntimeException('Tanggal acuan workbook pada sel C5 tidak valid.');
            }

            $batch->update([
                'tanggal_acuan' => $tanggalAcuan->toDateString(),
                'total_baris' => count($workbook['rows']),
            ]);

            $previousActiveImported = PumkPinjaman::query()
                ->whereNull('created_by')
                ->where('is_active', true)
                ->count();

            $summary = [
                'batch_id' => $batch->id,
                'total' => count($workbook['rows']),
                'berhasil' => 0,
                'gagal' => 0,
                'peringatan' => 0,
                'baru' => 0,
                'diperbarui' => 0,
                'tanpa_perubahan' => 0,
                'dinonaktifkan' => 0,
                'perbandingan_diperiksa' => 0,
                'perbandingan_berbeda' => 0,
                'matched' => 0,
                'conflict' => 0,
                'candidate_deactivation' => 0,
                'mitra_insert' => 0,
                'mitra_update' => 0,
                'pinjaman_insert' => 0,
                'pinjaman_update' => 0,
                'saldo_awal_update' => 0,
                'angsuran_import_insert' => 0,
                'angsuran_import_update' => 0,
                'angsuran_import_removal_candidate' => 0,
                'manual_angsuran_conflict' => 0,
            ];
            $seenPinjamanKeys = [];

            foreach ($workbook['rows'] as $sourceRow) {
                $worksheetRow = $sourceRow['worksheet_row'];
                $cells = $sourceRow['cells'];
                $rowHash = $this->rowHash($cells);

                try {
                    $result = DB::transaction(fn (): array => $this->importRow(
                        $cells,
                        $batch,
                        $tanggalAcuan,
                        $workbook['date_1904'],
                    ));

                    $result['warnings'] = array_values(array_unique(array_merge(
                        $result['warnings'],
                        $sourceRow['source_warnings'] ?? [],
                    )));

                    $status = $result['warnings'] === [] ? $result['change'] : 'needs_review';

                    PumkImportRow::create([
                        'batch_id' => $batch->id,
                        'source_row_number' => $worksheetRow,
                        'row_hash' => $rowHash,
                        'status' => $status,
                        'error_message' => $result['warnings'] === []
                            ? null
                            : json_encode(array_values(array_unique($result['warnings'])), JSON_UNESCAPED_SLASHES),
                    ]);

                    $summary['berhasil']++;
                    $summary[$this->summaryKey($result['change'])]++;
                    if ($result['warnings'] !== []) {
                        $summary['peringatan']++;
                    }
                    if ($result['comparison_checked']) {
                        $summary['perbandingan_diperiksa']++;
                    }
                    if ($result['comparison_differences'] !== []) {
                        $summary['perbandingan_berbeda']++;
                    }
                    foreach ($result['metrics'] as $metric => $value) {
                        $summary[$metric] += $value;
                    }

                    $seenPinjamanKeys[] = $result['pinjaman_key'];
                } catch (\Throwable $exception) {
                    PumkImportRow::create([
                        'batch_id' => $batch->id,
                        'source_row_number' => $worksheetRow,
                        'row_hash' => $rowHash,
                        'status' => 'failed',
                        'error_message' => $this->safeRowError($exception),
                    ]);

                    Log::warning('Baris impor PUMK gagal.', [
                        'batch_id' => $batch->id,
                        'worksheet_row' => $worksheetRow,
                        'exception' => $exception::class,
                    ]);

                    $summary['gagal']++;
                    if ($this->safeRowError($exception) === 'source_profile_conflict') {
                        $summary['conflict']++;
                    }
                }
            }

            $summary['candidate_deactivation'] = $this->countMissingSourceRows($seenPinjamanKeys);
            $deactivationSkipped = false;
            if ($summary['gagal'] === 0) {
                $coverageIsSafe = $previousActiveImported === 0
                    || count($seenPinjamanKeys) >= (int) ceil($previousActiveImported * 0.8);

                if ($coverageIsSafe) {
                    $summary['dinonaktifkan'] = $this->deactivateMissingSourceRows($seenPinjamanKeys);
                } else {
                    $deactivationSkipped = true;
                }
            } else {
                $deactivationSkipped = true;
            }

            $hasWarnings = $summary['peringatan'] > 0 || $summary['gagal'] > 0 || $deactivationSkipped;
            $batch->update([
                'status' => $hasWarnings ? 'completed_with_warnings' : 'completed',
                'berhasil' => $summary['berhasil'],
                'gagal' => $summary['gagal'],
                'completed_at' => now(),
                'error_summary' => $hasWarnings
                    ? json_encode([
                        'rows_needing_review' => $summary['peringatan'],
                        'failed_rows' => $summary['gagal'],
                        'missing_row_deactivation_skipped' => $deactivationSkipped,
                        'calculator_comparison_checked' => $summary['perbandingan_diperiksa'],
                        'calculator_comparison_different' => $summary['perbandingan_berbeda'],
                    ], JSON_UNESCAPED_SLASHES)
                    : null,
            ]);

            // Impor dapat mengubah baseline atau menghapus angsuran lama lewat
            // query builder (tanpa event model). Tandai snapshot untuk rekonsiliasi.
            if (($summary['baru'] + $summary['diperbarui'] + $summary['dinonaktifkan']) > 0
                && Schema::hasTable('pumk_monitoring_reports')) {
                DB::table('pumk_monitoring_reports')->update([
                    'needs_reconcile' => true,
                    'updated_at' => now(),
                ]);
            }

            return $summary;
        } catch (\Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_summary' => json_encode(['error' => $this->safeBatchError($exception)]),
            ]);

            Log::error('Impor PUMK gagal.', [
                'batch_id' => $batch->id,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException(
                "Impor PUMK gagal. Gunakan ID batch {$batch->id} untuk pemeriksaan log.",
                0,
                $exception,
            );
        }
    }

    /**
     * @param  array<string, ?string>  $cells
     * @return array{change:'new'|'updated'|'unchanged',warnings:list<string>,pinjaman_key:string,comparison_checked:bool,comparison_differences:list<string>,metrics:array<string,int>}
     */
    private function importRow(
        array $cells,
        PumkImportBatch $batch,
        CarbonImmutable $tanggalAcuan,
        bool $date1904,
    ): array {
        return $this->writer->importRow($cells, $batch, $tanggalAcuan, $date1904);
    }

    /** @param array<string, ?string> $cells */
    private function rowHash(array $cells): string
    {
        ksort($cells);
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY wajib tersedia untuk membuat audit hash.');
        }

        return hash_hmac('sha256', (string) json_encode($cells, JSON_UNESCAPED_UNICODE), $key);
    }

    private function deactivateMissingSourceRows(array $seenPinjamanKeys): int
    {
        $query = PumkPinjaman::query()->whereNull('created_by')->where('is_active', true);

        if ($seenPinjamanKeys !== []) {
            $query->whereNotIn('source_key', $seenPinjamanKeys);
        }

        $count = $query->update([
            'is_active' => false,
            'status' => PumkPinjaman::STATUS_NONAKTIF,
        ]);

        PumkMitra::query()
            ->whereNull('created_by')
            ->whereDoesntHave('pinjaman', fn ($query) => $query
                ->where('status', PumkPinjaman::STATUS_AKTIF)
                ->where('is_active', true))
            ->update(['is_active' => false]);

        PumkMitra::query()
            ->whereNull('created_by')
            ->whereHas('pinjaman', fn ($query) => $query
                ->where('status', PumkPinjaman::STATUS_AKTIF)
                ->where('is_active', true))
            ->update(['is_active' => true]);

        return $count;
    }

    private function countMissingSourceRows(array $seenPinjamanKeys): int
    {
        $query = PumkPinjaman::query()->whereNull('created_by')->where('is_active', true);

        if ($seenPinjamanKeys !== []) {
            $query->whereNotIn('source_key', $seenPinjamanKeys);
        }

        return $query->count();
    }

    private function summaryKey(string $change): string
    {
        return match ($change) {
            'new' => 'baru',
            'updated' => 'diperbarui',
            default => 'tanpa_perubahan',
        };
    }

    private function safeRowError(\Throwable $exception): string
    {
        if ($exception instanceof PumkImportRowException) {
            return $exception->getMessage();
        }

        return 'unexpected_row_error:'.class_basename($exception);
    }

    private function safeBatchError(\Throwable $exception): string
    {
        return 'unexpected_batch_error:'.class_basename($exception);
    }

    /** Normalisasi nilai sumber untuk audit dan perbaikan terbatas data impor. */
    public function sourceMoney(mixed $raw): ?string
    {
        return $this->parser->sourceMoney($raw);
    }

    /** Raw numeric value for read-only reconciliation, before cent formatting. */
    public function sourceDecimal(mixed $raw): ?string
    {
        return $this->parser->sourceDecimal($raw);
    }

    private function decimal(mixed $raw): ?string
    {
        return $this->parser->decimal($raw);
    }
}
