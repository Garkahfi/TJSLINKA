<?php

namespace App\Console\Commands;

use App\Models\PumkPinjaman;
use App\Services\Monitoring\PumkInternalMonitoringService;
use App\Services\Pumk\PumkLoanSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PumkAuditSettlements extends Command
{
    protected $signature = 'pumk:audit-settlements {--mitra= : Batasi berdasarkan ID mitra} {--json : Keluarkan JSON tanpa identitas pribadi}';

    protected $description = 'Periksa metadata pelunasan dan posisi monitoring tanpa mengubah data';

    public function handle(PumkLoanSettlementService $settlements, PumkInternalMonitoringService $monitoring): int
    {
        $id = $this->option('mitra');
        if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
            $this->error('ID mitra harus berupa bilangan bulat positif.');

            return self::INVALID;
        }
        $today = CarbonImmutable::now('Asia/Jakarta');
        $positions = $monitoring->positionForCapture($today->startOfDay());
        $loans = PumkPinjaman::with(['saldoAwal', 'angsuran', 'closures'])
            ->when($id !== null, fn ($query) => $query->where('mitra_id', (int) $id))->get();
        $rows = [];
        foreach ($loans as $loan) {
            if ($loan->isUnfundedVoid()) {
                $listedInMonitoring = in_array($loan->id, $positions['closed_ids'], true)
                    || collect($positions['rows'])->contains(fn ($row) => $row['pinjaman_id'] === $loan->id)
                    || collect($positions['unknown_details'])->contains(fn ($row) => $row['pinjaman_id'] === $loan->id);
                $rows[] = [
                    'mitra_id' => $loan->mitra_id, 'pinjaman_id' => $loan->id, 'status' => $loan->status,
                    'saldo_sumber' => null, 'saldo_saat_lunas' => null, 'alasan_lunas' => null,
                    'monitoring_closed' => false, 'eligible_now' => false,
                    'excluded_reason' => 'unfunded_void',
                    'issues' => $listedInMonitoring ? ['status_monitoring_mismatch'] : [],
                ];

                continue;
            }
            $preview = $settlements->preview($loan, $today);
            $issues = [];
            $closed = in_array($loan->id, $positions['closed_ids'], true);
            if ($loan->status === PumkPinjaman::STATUS_LUNAS) {
                if ($loan->is_active || ! $closed) {
                    $issues[] = 'status_monitoring_mismatch';
                }
                if ($loan->lunas_at === null || $loan->lunas_total_saldo === null || $loan->lunas_reason === null) {
                    $issues[] = 'settlement_metadata_missing';
                } else {
                    $p = $loan->lunas_saldo_pokok;
                    $b = $loan->lunas_saldo_bunga;
                    $total = $loan->lunas_total_saldo;
                    if ($p === null || $b === null || bccomp(bcadd($p, $b, 2), $total, 2) !== 0) {
                        $issues[] = 'settlement_components_mismatch';
                    } else {
                        $valid = match ($loan->lunas_reason) {
                            PumkPinjaman::LUNAS_NORMAL => bccomp($p, '0', 2) === 0 && bccomp($b, '0', 2) === 0,
                            PumkPinjaman::LUNAS_KELEBIHAN_BAYAR => bccomp($p, '0', 2) <= 0 && bccomp($b, '0', 2) <= 0 && bccomp($total, '0', 2) < 0,
                            PumkPinjaman::LUNAS_TOLERANSI => $loan->lunas_tolerance_applied !== null && bccomp($p, '0', 2) >= 0 && bccomp($b, '0', 2) >= 0 && bccomp($total, '0', 2) > 0 && bccomp($total, $loan->lunas_tolerance_applied, 2) <= 0,
                            default => false,
                        };
                        if (! $valid) {
                            $issues[] = 'settlement_reason_mismatch';
                        }
                    }
                    if ($loan->lunas_reason !== PumkPinjaman::LUNAS_NORMAL && blank($loan->lunas_note)) {
                        $issues[] = 'settlement_note_missing';
                    }
                    if ($preview['known'] && bccomp($preview['total'], $total, 2) !== 0) {
                        $issues[] = 'source_changed_since_closure';
                    }
                }
            }
            if (! $preview['known']) {
                $issues[] = $preview['reason_code'];
            } elseif ($preview['reason_code'] === 'card_balance_mismatch') {
                $issues[] = 'card_balance_mismatch';
            }
            $rows[] = [
                'mitra_id' => $loan->mitra_id, 'pinjaman_id' => $loan->id, 'status' => $loan->status,
                'saldo_sumber' => $preview['total'], 'saldo_saat_lunas' => $loan->lunas_total_saldo,
                'alasan_lunas' => $loan->lunas_reason, 'monitoring_closed' => $closed,
                'eligible_now' => $loan->status === PumkPinjaman::STATUS_AKTIF && $loan->is_active && $preview['eligible'],
                'issues' => array_values(array_unique($issues)),
            ];
        }
        $result = ['as_of_date' => $today->toDateString(), 'loans_checked' => count($rows), 'items' => $rows];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Mitra ID', 'Pinjaman ID', 'Status', 'Saldo sumber', 'Saldo tutup', 'Monitoring ditutup', 'Temuan'],
                array_map(fn ($row) => [$row['mitra_id'], $row['pinjaman_id'], $row['status'], $row['saldo_sumber'] ?? 'unknown',
                    $row['saldo_saat_lunas'] ?? '-', $row['monitoring_closed'] ? 'ya' : 'tidak', implode(', ', $row['issues']) ?: '-'], $rows));
        }

        return collect($rows)->contains(fn ($row) => $row['issues'] !== []) ? self::FAILURE : self::SUCCESS;
    }
}
