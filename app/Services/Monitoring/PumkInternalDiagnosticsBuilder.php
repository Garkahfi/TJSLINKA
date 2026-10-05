<?php

namespace App\Services\Monitoring;

use App\Models\PumkMonitoringReport;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;

class PumkInternalDiagnosticsBuilder
{
    public function __construct(
        private readonly PumkInternalPositionService $positions,
        private readonly PumkInternalTimeline $timeline,
    ) {}

    /** @return array{report:array<string,mixed>,items:list<array<string,mixed>>} */
    public function build(array $report): array
    {
        if ($report['as_of_date'] === null) {
            return ['report' => $report, 'items' => []];
        }
        $asOf = CarbonImmutable::parse($report['as_of_date'], 'Asia/Jakarta')->startOfDay();
        $loans = PumkPinjaman::query()->with([
            'mitra.sektorUsaha', 'mitra.wilayah', 'mitra.classificationHistory',
            'classificationHistory', 'saldoAwal', 'angsuran', 'closures',
        ])->get();
        $reports = PumkMonitoringReport::query()->with('positions')
            ->whereDate('as_of_date', '<=', $asOf->toDateString())->orderBy('as_of_date')->get();
        $positions = $this->positions->positionsAt($loans, $asOf, $reports);
        $loanById = $loans->keyBy('id');
        $items = [];
        foreach ($positions['unknown_details'] as $item) {
            $loan = $loanById->get($item['pinjaman_id']);
            $items[] = $this->diagnosticItem($loan, $asOf, $item['reason_code'], $item['source_kind'] ?? 'unknown');
        }
        foreach ($positions['rows'] as $row) {
            $loan = $loanById->get($row['pinjaman_id']);
            foreach ($row['classification_reasons'] as $attribute => $reason) {
                if ($reason === null) {
                    continue;
                }
                $category = $attribute === 'provinsi' ? 'wilayah' : $attribute;
                $items[] = $this->diagnosticItem($loan, $asOf, $reason,
                    $row['classification_sources'][$category], $row,
                    $row['classification_dates'][$category], $attribute);
            }
            $principalSign = bccomp($row['saldo_pokok'], '0', 2);
            $interestSign = bccomp($row['saldo_bunga'], '0', 2);
            if ($principalSign * $interestSign < 0) {
                $items[] = $this->diagnosticItem($loan, $asOf, 'mixed_component_balance', $row['source_kind'], $row);
            } elseif (bccomp($row['total'], '0', 2) < 0) {
                $items[] = $this->diagnosticItem($loan, $asOf, 'negative_balance_unreviewed', $row['source_kind'], $row);
            }
        }
        foreach ($loans as $loan) {
            if ((int) $loan->tahun_pencairan === 1900
                || $loan->tanggal_pencairan?->year === 1900
                || $this->timeline->loanStartDate($loan) === null) {
                $items[] = $this->diagnosticItem($loan, $asOf, 'invalid_source_date', 'source_date');
            }
        }

        return ['report' => $report, 'items' => $items];
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function diagnosticItem(PumkPinjaman $loan, CarbonImmutable $asOf, string $reason, string $sourceKind, array $row = [], ?string $sourceDate = null, ?string $attribute = null): array
    {
        return [
            'pinjaman_id' => $loan->id, 'mitra_id' => $loan->mitra_id,
            'nama_mitra' => $loan->mitra?->nama_mitra,
            'as_of_date' => $asOf->toDateString(), 'reason_code' => $reason,
            'attribute' => $attribute,
            'source_kind' => $sourceKind, 'source_date' => $sourceDate ?? $loan->source_updated_at?->toDateString(),
            'saldo_pokok' => $row['saldo_pokok'] ?? null,
            'saldo_bunga' => $row['saldo_bunga'] ?? null,
            'total' => $row['total'] ?? null,
        ];
    }
}
