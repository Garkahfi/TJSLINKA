<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use RuntimeException;

/** Read-only evidence; this service never repairs, reimports or synchronizes caches. */
class PumkPiutangReconciliationService
{
    public function __construct(
        private readonly PumkImportService $importer,
        private readonly PiutangCalculator $calculator,
        private readonly PumkCollectibilitySummaryService $summary,
    ) {}

    /** @param array<string,mixed> $workbook
     * @return array<string,mixed>
     */
    public function reconcile(array $workbook, ?int $mitraId = null): array
    {
        $sources = [];
        foreach ($workbook['rows'] as $row) {
            $no = trim((string) ($row['cells']['A'] ?? ''));
            // Excel serializes these identifiers as 1.0 as well as 1.
            // Accept only integral values; never round a fractional source ID.
            if (! preg_match('/^\d+(?:\.0+)?$/', $no) || (int) $no < 1
                || bccomp($no, (string) PHP_INT_MAX, 0) > 0) {
                throw new RuntimeException('Nomor urut sumber tidak valid pada baris '.$row['worksheet_row'].'.');
            }
            $key = hash('sha256', 'db-pumk-v1|pinjaman|'.(int) $no);
            if (isset($sources[$key])) {
                throw new RuntimeException('Nomor urut sumber duplikat pada baris '.$row['worksheet_row'].'.');
            }
            $cells = $row['cells'];
            $principal = $this->importer->sourceMoney($cells['AU'] ?? null);
            $interest = $this->importer->sourceMoney($cells['AV'] ?? null);
            $total = $this->importer->sourceMoney($cells['AW'] ?? null);
            $components = $principal !== null && $interest !== null ? bcadd($principal, $interest, 2) : null;
            $sources[$key] = [
                'source_no' => (int) $no, 'worksheet_row' => $row['worksheet_row'],
                'category' => PumkCollectibilitySummaryService::normalizeCategory($cells['AT'] ?? null),
                'category_raw' => $cells['AT'] ?? null,
                'principal' => $principal, 'interest' => $interest, 'total_aw' => $total,
                'total_components' => $components,
                'components_minus_aw' => $this->difference($components, $total),
                'warnings' => $row['source_warnings'] ?? [],
            ];
        }

        $rows = [];
        $matched = [];
        $groups = [];
        $loans = PumkPinjaman::query()->when($mitraId !== null, fn ($query) => $query->where('mitra_id', $mitraId))
            ->with(['saldoAwal', 'angsuran', 'closures', 'classificationHistory'])->orderBy('id')->get();
        foreach ($loans as $loan) {
            $source = $sources[$loan->source_key] ?? null;
            if ($source !== null) {
                $matched[$loan->source_key] = true;
            }
            $card = $this->calculator->hitungUntukPinjaman($loan);
            $position = $this->summary->positionForLoan($loan);
            $included = ($loan->status === PumkPinjaman::STATUS_AKTIF && $loan->is_active)
                || $loan->status === PumkPinjaman::STATUS_LUNAS;
            $closure = $loan->closures->whereNull('reopened_at')->sortByDesc('id')->first();
            $baseline = $loan->baseline_sumber;
            $delta = [];
            foreach (['pokok', 'bunga', 'denda'] as $component) {
                $field = 'total_'.$component.'_masuk';
                $delta[$component] = $this->difference($card[$field], $baseline[$field] ?? null);
            }
            $flags = [];
            if ($source === null) {
                $flags[] = 'not_in_workbook';
            }
            if ($position['balance'] === null) {
                $flags[] = 'balance_unproven';
            }
            if ($position['cache_difference']) {
                $flags[] = 'cache_differs_from_card';
            }
            if ($source !== null && $source['category'] !== $position['category']) {
                $flags[] = 'category_differs_from_source';
            }
            if ($source !== null && $this->difference($position['balance'], $source['total_aw']) !== '0.00') {
                $flags[] = 'recap_differs_from_source';
            }
            if (($loan->status === PumkPinjaman::STATUS_AKTIF) !== $loan->is_active) {
                $flags[] = 'status_flag_mismatch';
            }
            $rows[] = [
                'pinjaman_id' => $loan->id, 'mitra_id' => $loan->mitra_id,
                'source' => $source, 'status' => $loan->status, 'is_active' => $loan->is_active,
                'included_in_recap' => $included, 'source_updated_at' => $loan->source_updated_at?->toIso8601String(),
                'stored_category' => $loan->kolektibilitas, 'recap_category' => $position['category'],
                'cached_total' => $loan->total_sisa, 'card_total' => $card['total_sisa'],
                'recap_total' => $position['balance'],
                'recap_minus_source_aw' => $this->difference($position['balance'], $source['total_aw'] ?? null),
                'card_minus_source_components' => $this->difference($card['total_sisa'], $source['total_components'] ?? null),
                'baseline' => $baseline, 'net_payment_change_from_baseline' => $delta,
                'closing_total' => $loan->lunas_total_saldo,
                'closed_at' => $loan->lunas_at?->toIso8601String(),
                'closure_id' => $closure?->id,
                'closure_closed_at' => $closure?->closed_at?->toIso8601String(),
                'closure_total' => $closure?->settlement_snapshot['lunas_total_saldo'] ?? null,
                'closure_category' => $closure?->settlement_snapshot['kolektibilitas'] ?? null,
                'flags' => $flags,
                'payments' => $loan->angsuran->sortBy('id')->map(fn ($payment) => [
                    'id' => $payment->id, 'periode' => $payment->periode?->toDateString(),
                    'pokok' => $payment->pokok, 'bunga' => $payment->bunga, 'denda' => $payment->denda,
                    'batch_id' => $payment->batch_id,
                    'created_at' => $payment->created_at?->toIso8601String(),
                    'updated_at' => $payment->updated_at?->toIso8601String(),
                ])->values()->all(),
            ];
            if ($included) {
                $category = $position['category'];
                $groups[$category] ??= ['loans' => 0, 'known_total' => '0.00', 'unknown_balances' => 0];
                $groups[$category]['loans']++;
                if ($position['balance'] === null) {
                    $groups[$category]['unknown_balances']++;
                } else {
                    $groups[$category]['known_total'] = bcadd($groups[$category]['known_total'], $position['balance'], 2);
                }
            }
        }

        return [
            'sheet' => $workbook['sheet_name'], 'source_date_raw' => $workbook['tanggal_acuan_raw'],
            'source_rows' => count($sources), 'database_loans' => count($rows),
            'scope_mitra_id' => $mitraId, 'recap' => $groups, 'loans' => $rows,
            // A partner-specific audit cannot prove that other workbook rows are missing globally.
            'source_rows_missing_in_database' => $mitraId === null ? array_values(array_diff_key($sources, $matched)) : [],
        ];
    }

    private function difference(?string $left, ?string $right): ?string
    {
        return $left !== null && $right !== null ? bcsub($left, $right, 2) : null;
    }
}
