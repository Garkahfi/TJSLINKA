<?php

namespace App\Console\Commands;

use App\Models\PumkImportRow;
use App\Models\User;
use App\Services\Pumk\DuplicatePumkImportException;
use App\Services\Pumk\PumkImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PumkImport extends Command
{
    protected $signature = 'pumk:import
        {file : Path lengkap file XLSX PUMK}
        {--user= : ID atau username Super Admin yang menjalankan impor}
        {--dry-run : Jalankan seluruh validasi dan upsert lalu rollback tanpa menyimpan perubahan}';

    protected $description = 'Impor workbook kartu piutang PUMK secara idempoten dan teraudit';

    public function handle(PumkImportService $service): int
    {
        $path = (string) $this->argument('file');
        $importer = $this->resolveImporter($this->option('user'));

        if ($this->option('user') !== null && $importer === null) {
            $this->error('Super Admin pada opsi --user tidak ditemukan atau tidak berwenang.');

            return self::FAILURE;
        }

        try {
            $dryRunFailures = collect();
            if ($this->option('dry-run')) {
                DB::beginTransaction();
                try {
                    $summary = $service->import($path, $importer);
                    $dryRunFailures = PumkImportRow::query()
                        ->where('batch_id', $summary['batch_id'])
                        ->where('status', 'failed')
                        ->get(['source_row_number', 'error_message']);
                } finally {
                    DB::rollBack();
                }
            } else {
                $summary = $service->import($path, $importer);
            }
        } catch (DuplicatePumkImportException $exception) {
            $this->warn($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($this->option('dry-run')
            ? 'Dry-run PUMK selesai. Seluruh perubahan database sudah di-rollback.'
            : 'Impor PUMK selesai.');
        $this->line('Schema Reschedule Ke-4: '.(Schema::hasColumn('pumk_pinjaman', 'reschedule_ke4') ? 'siap' : 'migration diperlukan'));
        $this->table(
            [
                'Batch',
                'Total',
                'Berhasil',
                'Gagal',
                'Perlu review',
                'Baru',
                'Diperbarui',
                'Tanpa perubahan',
                'Dinonaktifkan',
                'Audit kalkulator',
                'Ada selisih',
            ],
            [[
                $summary['batch_id'],
                $summary['total'],
                $summary['berhasil'],
                $summary['gagal'],
                $summary['peringatan'],
                $summary['baru'],
                $summary['diperbarui'],
                $summary['tanpa_perubahan'],
                $summary['dinonaktifkan'],
                $summary['perbandingan_diperiksa'],
                $summary['perbandingan_berbeda'],
            ]],
        );
        $this->table(
            ['Rincian upsert', 'Jumlah'],
            [
                ['Matched', $summary['matched']],
                ['Conflict', $summary['conflict']],
                ['Candidate deactivation', $summary['candidate_deactivation']],
                ['Mitra insert / update', $summary['mitra_insert'].' / '.$summary['mitra_update']],
                ['Pinjaman insert / update', $summary['pinjaman_insert'].' / '.$summary['pinjaman_update']],
                ['Saldo awal update', $summary['saldo_awal_update']],
                ['Angsuran import insert / update', $summary['angsuran_import_insert'].' / '.$summary['angsuran_import_update']],
                ['Angsuran import removal candidate', $summary['angsuran_import_removal_candidate']],
                ['Konflik angsuran manual', $summary['manual_angsuran_conflict']],
            ],
        );

        if ($summary['perbandingan_berbeda'] > 0) {
            $this->warn(
                'Nilai sumber Excel tetap dipertahankan. Periksa baris needs_review sebelum rumus kalkulator digunakan sebagai pengganti baseline.',
            );
        }

        if ($this->option('dry-run') && $dryRunFailures->isNotEmpty()) {
            $this->warn('Baris gagal pada dry-run (tanpa data pribadi):');
            $this->table(
                ['Kode', 'Jumlah', 'Baris worksheet'],
                $dryRunFailures
                    ->groupBy('error_message')
                    ->map(fn ($rows, $code) => [
                        $code,
                        $rows->count(),
                        $rows->pluck('source_row_number')->implode(', '),
                    ])
                    ->values()
                    ->all(),
            );
        }

        return $summary['gagal'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveImporter(mixed $identifier): ?User
    {
        if ($identifier === null || trim((string) $identifier) === '') {
            return null;
        }

        $value = trim((string) $identifier);

        return User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->where(function ($query) use ($value): void {
                if (ctype_digit($value)) {
                    $query->whereKey((int) $value)->orWhere('username', $value);
                } else {
                    $query->where('username', $value);
                }
            })
            ->first();
    }
}
