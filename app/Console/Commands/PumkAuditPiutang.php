<?php

namespace App\Console\Commands;

use App\Services\Pumk\PumkPiutangReconciliationService;
use App\Services\Pumk\PumkXlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PumkAuditPiutang extends Command
{
    protected $signature = 'pumk:audit-piutang {file : Workbook sumber SPJ} {--mitra= : Batasi ID mitra} {--json : Tampilkan bukti rinci JSON}';

    protected $description = 'Bandingkan sumber SPJ, kartu, cache, pembayaran, dan saldo penutupan tanpa mengubah database';

    public function handle(PumkXlsxReader $reader, PumkPiutangReconciliationService $audit): int
    {
        $id = $this->option('mitra');
        if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
            $this->error('ID mitra harus bilangan bulat positif.');

            return self::INVALID;
        }
        try {
            $path = (string) $this->argument('file');
            $workbook = $reader->read($path);
            $report = DB::transaction(fn () => $audit->reconcile($workbook, $id === null ? null : (int) $id));
            $report['file_sha256'] = hash_file('sha256', $path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Audit baca-saja: '.$report['sheet']);
            $this->table(['Pinjaman', 'Mitra', 'No sumber', 'Kategori rekap', 'Sumber AW', 'Kartu', 'Rekap', 'Selisih', 'Temuan'],
                array_map(fn ($row) => [
                    $row['pinjaman_id'], $row['mitra_id'], $row['source']['source_no'] ?? '-',
                    $row['recap_category'], $row['source']['total_aw'] ?? '?', $row['card_total'],
                    $row['recap_total'] ?? '?', $row['recap_minus_source_aw'] ?? '?', implode(', ', $row['flags']),
                ], $report['loans']));
            $this->line('Baris sumber belum ditemukan: '.count($report['source_rows_missing_in_database']));
            $this->line('Gunakan --json untuk baseline, perubahan pembayaran, metadata penutupan, dan subtotal.');
        }

        return self::SUCCESS;
    }
}
