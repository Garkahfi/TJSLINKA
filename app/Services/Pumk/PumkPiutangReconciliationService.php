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
            $rawTotal = $this->importer->sourceDecimal($cells['AW'] ?? null);
            $components = $principal !== null && $interest !== null ? bcadd($principal, $interest, 2) : null;
            $sources[$key] = [
                'source_no' => (int) $no, 'worksheet_row' => $row['worksheet_row'],
                'tanggal_acuan_aj' => $cells['AJ'] ?? null,
                'mulai_angsuran_ah' => $cells['AH'] ?? null,
                'total_kewajiban_ag' => $this->importer->sourceDecimal($cells['AG'] ?? null),
                'angsuran_bulanan_ak' => $cells['AK'] ?? null,
                'jatuh_tempo_bulan_ap' => $cells['AP'] ?? null,
                'jatuh_tempo_nominal_aq' => $this->importer->sourceMoney($cells['AQ'] ?? null),
                'jatuh_tempo_nominal_aq_raw' => $this->importer->sourceDecimal($cells['AQ'] ?? null),
                'pokok_masuk_al' => $this->importer->sourceMoney($cells['AL'] ?? null),
                'pokok_masuk_al_raw' => $this->importer->sourceDecimal($cells['AL'] ?? null),
                'bunga_masuk_am' => $this->importer->sourceMoney($cells['AM'] ?? null),
                'bunga_masuk_am_raw' => $this->importer->sourceDecimal($cells['AM'] ?? null),
                'pokok_bunga_masuk_an' => $this->importer->sourceMoney($cells['AN'] ?? null),
                'pokok_bunga_masuk_an_raw' => $this->importer->sourceDecimal($cells['AN'] ?? null),
                'tunggakan_bulan_ar' => $cells['AR'] ?? null,
                'tunggakan_dibulatkan_as' => $this->importer->sourceMoney($cells['AS'] ?? null),
                'category' => PumkCollectibilitySummaryService::normalizeCategory($cells['AT'] ?? null),
                'category_raw' => $cells['AT'] ?? null,
                'principal' => $principal, 'interest' => $interest, 'total_aw' => $total,
                'principal_raw' => $this->importer->sourceDecimal($cells['AU'] ?? null),
                'interest_raw' => $this->importer->sourceDecimal($cells['AV'] ?? null),
                'total_aw_raw' => $rawTotal,
                'total_components' => $components,
                'components_minus_aw' => $this->difference($components, $total),
                'warnings' => $row['source_warnings'] ?? [],
            ];
        }

        $rows = [];
        $matched = [];
        $groups = [];
        $sourceGroups = [];
        foreach ($sources as $source) {
            $category = $source['category'];
            $sourceGroups[$category] ??= ['loans' => 0, 'raw_total' => '0.00'];
            $sourceGroups[$category]['loans']++;
            if ($source['total_aw_raw'] !== null) {
                $sourceGroups[$category]['raw_total'] = bcadd($sourceGroups[$category]['raw_total'], $source['total_aw_raw'], 20);
            }
        }
        foreach ($sourceGroups as &$group) {
            $group['display_total'] = PumkDecimal::roundCents($group['raw_total']);
        }
        unset($group);
        $loans = PumkPinjaman::query()->when($mitraId !== null, fn ($query) => $query->where('mitra_id', $mitraId))
            ->with(['saldoAwal', 'angsuran', 'closures', 'classificationHistory'])->orderBy('id')->get();
        foreach ($loans as $loan) {
            $source = $sources[$loan->source_key] ?? null;
            if ($source !== null) {
                $matched[$loan->source_key] = true;
            }
            $card = $this->calculator->hitungUntukPinjaman($loan);
            $position = $this->summary->positionForLoan($loan);
            $included = $this->summary->isIncluded($loan);
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
            if (in_array($loan->source_key, config('pumk.recap_excluded_source_keys', []), true)) {
                $flags[] = 'excluded_verified_dummy';
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
                'card_tanggal_acuan' => $card['tanggal_acuan'],
                'card_jatuh_tempo_bulan_ap' => $card['jumlah_jatuh_tempo'],
                'card_jatuh_tempo_nominal_aq' => $card['jatuh_tempo_nominal'],
                'card_tunggakan_bulan_ar' => $card['bulan_tunggakan'],
                'card_category_at' => $card['kolektibilitas'],
                'recap_total' => $position['balance'],
                'card_total_raw' => $card['total_sisa_raw'],
                'recap_total_raw' => $position['raw_balance'] ?? null,
                'recap_minus_source_aw' => $this->difference($position['balance'], $source['total_aw'] ?? null),
                'raw_minus_source_aw' => $this->rawDifference($position['raw_balance'] ?? null, $source['total_aw_raw'] ?? null),
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
                    'created_by' => $payment->created_by,
                    'has_receipt_reference' => filled($payment->nomor_bukti),
                    'has_payment_proof' => filled($payment->bukti_pembayaran_path),
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
                    $groups[$category]['known_total'] = bcadd(
                        $groups[$category]['known_total'], $position['raw_balance'] ?? $position['balance'], 20,
                    );
                }
            }
        }
        foreach ($groups as &$group) {
            $group['known_total'] = PumkDecimal::roundCents($group['known_total']);
        }
        unset($group);

        return [
            'sheet' => $workbook['sheet_name'], 'source_date_raw' => $workbook['tanggal_acuan_raw'],
            'source_rows' => count($sources), 'database_loans' => count($rows),
            'scope_mitra_id' => $mitraId, 'source_subtotals' => $sourceGroups,
            'recap' => $groups, 'loans' => $rows,
            // A partner-specific audit cannot prove that other workbook rows are missing globally.
            'source_rows_missing_in_database' => $mitraId === null ? array_values(array_diff_key($sources, $matched)) : [],
        ];
    }

    private function difference(?string $left, ?string $right): ?string
    {
        return $left !== null && $right !== null ? bcsub($left, $right, 2) : null;
    }

    private function rawDifference(?string $left, ?string $right): ?string
    {
        return $left !== null && $right !== null
            ? bcsub($left, $right, PumkDecimal::scale($left, $right))
            : null;
    }
}
