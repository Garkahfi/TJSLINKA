<?php

namespace App\Console\Commands;

use App\Services\Pumk\PumkBriSnapshotImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class PumkBriImport extends Command
{
    protected $signature = 'pumkbri:import
        {file : Path file workbook .xlsx}
        {--year= : Tahun default untuk sheet yang namanya tidak memuat tahun}
        {--force : Impor ulang dan perbarui periode yang sudah ada}';

    protected $description = 'Import master mitra dan snapshot bulanan PUMK BRI dari workbook Excel';

    public function handle(PumkBriSnapshotImportService $importer): int
    {
        $path = realpath((string) $this->argument('file'));
        if ($path === false || ! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            $this->error('File .xlsx tidak ditemukan.');

            return self::FAILURE;
        }
        $year = filter_var($this->option('year'), FILTER_VALIDATE_INT);
        if ($year === false || $year === null) {
            $this->error('Opsi --year wajib diisi, contoh: --year=2026.');

            return self::FAILURE;
        }

        try {
            $summary = $importer->import($path, $year, (bool) $this->option('force'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Import gagal karena kesalahan sistem. Periksa storage/logs/laravel.log.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($summary as $sheet => $result) {
            $period = $result['bulan'] === null ? '-' : sprintf('%02d/%d', $result['bulan'], $result['tahun']);
            $line = sprintf(
                '%s [%s] %s - %d baris, saldo Rp%s. %s',
                $sheet,
                $period,
                strtoupper($result['status']),
                $result['baris'],
                number_format((float) $result['total_saldo'], 0, ',', '.'),
                $result['pesan'],
            );

            if (in_array($result['status'], ['failed', 'needs_review'], true)) {
                $failed = true;
                $this->error($line);
            } elseif ($result['status'] === 'imported') {
                $this->info($line);
            } else {
                $this->warn($line);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
