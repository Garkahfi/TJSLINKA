<?php

namespace App\Console\Commands;

use App\Models\PumkImportBatch;
use App\Services\Pumk\PumkScientificMoneyRepair;
use App\Services\Pumk\PumkXlsxReader;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class PumkRepairScientificMoney extends Command
{
    protected $signature = 'pumk:repair-scientific-money
        {file : Workbook PUMK Internal yang sudah diimpor}
        {--expected-hash= : SHA-256 sumber yang telah diperiksa}
        {--apply : Terapkan koreksi setelah dry-run dan backup}
        {--backup= : Path dump SQL yang dibuat sebelum koreksi}';

    protected $description = 'Audit atau koreksi khusus nilai notasi ilmiah yang pernah dipotong saat import PUMK Internal';

    public function handle(PumkXlsxReader $reader, PumkScientificMoneyRepair $repair): int
    {
        try {
            $path = (string) $this->argument('file');
            $hash = is_file($path) ? hash_file('sha256', $path) : false;
            if ($hash === false || ! hash_equals((string) $this->option('expected-hash'), $hash)) {
                throw new RuntimeException('File tidak ditemukan atau SHA-256 tidak sama dengan --expected-hash.');
            }
            $batch = PumkImportBatch::query()->where('file_hash', $hash)->first();
            if ($batch === null || $batch->gagal !== 0 || $batch->berhasil !== $batch->total_baris) {
                throw new RuntimeException('Batch impor sumber tidak lengkap; koreksi tidak aman.');
            }
            $workbook = $reader->read($path);
            if (count($workbook['rows']) !== $batch->total_baris) {
                throw new RuntimeException('Jumlah baris workbook berbeda dari batch impor.');
            }

            $apply = (bool) $this->option('apply');
            if ($apply) {
                $backup = realpath((string) $this->option('backup'));
                $backupDirectory = realpath(storage_path('backups'));
                if ($backup === false || $backupDirectory === false
                    || ! str_starts_with(strtolower($backup), strtolower($backupDirectory.DIRECTORY_SEPARATOR))
                    || ! str_ends_with(strtolower($backup), '.sql') || filesize($backup) < 1024) {
                    throw new RuntimeException('Opsi --apply memerlukan dump SQL yang valid di storage/backups.');
                }
            }

            $result = $repair->run($workbook['rows'], $apply);
            $this->table(['Mode', 'Baris', 'Pokok kontrak', 'Pokok saldo awal', 'Konflik'], [[
                $apply ? 'Diterapkan' : 'Dry-run', $result['rows'], $result['principal'],
                $result['opening_principal'], count($result['conflicts']),
            ]]);
            if ($result['conflicts'] !== []) {
                $this->warn('Nomor sumber perlu review: '.implode(', ', $result['conflicts']));

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
