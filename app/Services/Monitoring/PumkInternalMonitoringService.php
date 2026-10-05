<?php

namespace App\Services\Monitoring;

use App\Models\PumkMonitoringReport;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PumkInternalMonitoringService
{
    public const MIN_REPORT_YEAR = 2025;

    public function __construct(
        private readonly PumkInternalTimeline $timeline,
        private readonly PumkInternalPositionService $positions,
        private readonly PumkInternalTrendBuilder $trendBuilder,
        private readonly PumkInternalDiagnosticsBuilder $diagnosticsBuilder,
    ) {}

    /** @return array<string, mixed> */
    public function report(?int $requestedYear = null): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        if ($requestedYear !== null && ($requestedYear < self::MIN_REPORT_YEAR || $requestedYear > $today->year)) {
            throw new InvalidArgumentException('Tahun laporan PUMK INKA di luar rentang yang tersedia.');
        }
        $loans = PumkPinjaman::query()->with([
            'mitra.sektorUsaha', 'mitra.wilayah', 'mitra.classificationHistory',
            'classificationHistory', 'saldoAwal', 'angsuran', 'closures',
        ])->get();
        $reports = PumkMonitoringReport::query()->with('positions')->whereDate('as_of_date', '<=', $today->toDateString())->orderBy('as_of_date')->get();
        $evidence = $this->timeline->evidenceDates($loans, $reports, $today);
        $years = collect(range(self::MIN_REPORT_YEAR, $today->year))->reverse()->values();
        $year = $requestedYear ?? $today->year;

        $yearStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta');
        $yearEnd = CarbonImmutable::create($year, 12, 31, 0, 0, 0, 'Asia/Jakarta')->min($today);
        $inYear = $evidence->filter(fn (CarbonImmutable $date): bool => $date->betweenIncluded($yearStart, $yearEnd));
        $asOf = $inYear->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->last()
            ?? $evidence->filter(fn (CarbonImmutable $date): bool => $date->lessThan($yearStart))
                ->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->last();
        if ($asOf === null) {
            return $this->emptyReport($year, $years->all());
        }

        $positions = $this->positionsAt($loans, $asOf, $reports);
        $rows = collect($positions['rows']);
        $positiveRows = $rows->filter(fn (array $row): bool => bccomp($row['total'], '0', 2) > 0);
        $negativeRows = $rows->filter(fn (array $row): bool => bccomp($row['total'], '0', 2) < 0);
        $hasKnownPosition = $rows->isNotEmpty() || $positions['closed'] > 0;
        $principal = $positiveRows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['saldo_pokok'], 2), '0.00');
        $interest = $positiveRows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['saldo_bunga'], 2), '0.00');
        $total = bcadd($principal, $interest, 2);
        $negativeTotal = $negativeRows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['total'], 2), '0.00');
        $group = fn (string $field) => $positiveRows->groupBy($field)
            ->map(fn (Collection $items, string $label): array => [
                'label' => $label,
                'jumlah' => $items->pluck('mitra_id')->unique()->count(),
                'nilai' => (float) $items->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['total'], 2), '0.00'),
            ])->sortKeys()->values();

        $activity = $loans->flatMap(fn (PumkPinjaman $loan) => $loan->angsuran)
            ->filter(fn ($payment): bool => $payment->periode !== null
                && $payment->periode->year === $year
                && $payment->periode->lessThanOrEqualTo($asOf));
        $updatedAt = $this->timeline->lastSourceUpdate($loans, $asOf, $reports);
        $status = $positions['unknown'] > 0 ? 'partial' : ($inYear->isEmpty() ? 'carryover' : 'available');

        return [
            'year' => $year,
            'years' => $years->all(),
            'status' => $status,
            'carried_from_previous_year' => $inYear->isEmpty(),
            'as_of_date' => $asOf->toDateString(),
            'updated_at' => $updatedAt,
            'known_loans' => $rows->count(),
            'closed_loans' => $positions['closed'],
            'positive_loans' => $positiveRows->count(),
            'unknown_loans' => $positions['unknown'],
            'negative_loans' => $negativeRows->count(),
            'negative_total' => (float) $negativeTotal,
            'net_known_balance' => $hasKnownPosition ? (float) bcadd($total, $negativeTotal, 2) : null,
            'payment_count' => $activity->count(),
            'activity_pokok' => $activity->reduce(fn (string $sum, $payment): string => bcadd($sum, (string) $payment->pokok, 2), '0.00'),
            'activity_bunga' => $activity->reduce(fn (string $sum, $payment): string => bcadd($sum, (string) $payment->bunga, 2), '0.00'),
            'saldo_pokok' => $hasKnownPosition ? (float) $principal : null,
            'saldo_bunga' => $hasKnownPosition ? (float) $interest : null,
            'total_saldo_piutang' => $hasKnownPosition ? (float) $total : null,
            'total_binaan' => $hasKnownPosition
                ? $positiveRows->pluck('mitra_id')->unique()->count()
                : null,
            'sektor' => $group('sektor'),
            'kolektibilitas' => $group('kolektibilitas'),
            'sebaran_provinsi' => $group('provinsi'),
            'tren_kolektibilitas' => $this->trendBuilder->trend($year, $yearEnd, $loans, $reports, $evidence, $asOf, $positions),
            'classification_limited' => $positiveRows->contains(fn (array $row): bool => $row['classification_limited']),
            'snapshot_revision' => $reports->first(fn (PumkMonitoringReport $report): bool => $report->as_of_date->toDateString() === $asOf->toDateString())?->revision,
            'snapshot_stale' => (bool) $reports->first(fn (PumkMonitoringReport $report): bool => $report->as_of_date->toDateString() === $asOf->toDateString())?->needs_reconcile,
        ];
    }

    /** @return array{rows:list<array<string,mixed>>,unknown:int,source_updated_at:?string} */
    public function positionForCapture(CarbonImmutable $asOf): array
    {
        $loans = PumkPinjaman::query()->with([
            'mitra.sektorUsaha', 'mitra.wilayah', 'mitra.classificationHistory',
            'classificationHistory', 'saldoAwal', 'angsuran', 'closures',
        ])->get();
        $reports = PumkMonitoringReport::query()->with('positions')
            ->whereDate('as_of_date', '<=', $asOf->toDateString())->orderBy('as_of_date')->get();
        $positions = $this->positionsAt($loans, $asOf, $reports);

        return $positions + ['source_updated_at' => $this->timeline->lastSourceUpdate($loans, $asOf, $reports)];
    }

    /** @return array{report:array<string,mixed>,items:list<array<string,mixed>>} */
    public function diagnostics(?int $year = null): array
    {
        $report = $this->report($year);

        return $this->diagnosticsBuilder->build($report);
    }

    /**
     * @param  EloquentCollection<int, PumkPinjaman>  $loans
     * @param  EloquentCollection<int, PumkMonitoringReport>  $reports
     * @return array{rows:list<array<string, mixed>>,unknown:int,closed:int,closed_ids:list<int>,unknown_details:list<array<string,mixed>>}
     */
    public function positionsAt(EloquentCollection $loans, CarbonImmutable $asOf, EloquentCollection $reports): array
    {
        return $this->positions->positionsAt($loans, $asOf, $reports);
    }

    /** @return array<string, mixed> */
    private function emptyReport(?int $year, array $years): array
    {
        return [
            'year' => $year, 'years' => $years, 'status' => 'unavailable', 'carried_from_previous_year' => false, 'as_of_date' => null,
            'updated_at' => null, 'known_loans' => 0, 'closed_loans' => 0, 'positive_loans' => 0,
            'unknown_loans' => 0, 'negative_loans' => 0, 'negative_total' => 0.0, 'net_known_balance' => null,
            'payment_count' => 0, 'activity_pokok' => '0.00', 'activity_bunga' => '0.00',
            'saldo_pokok' => null, 'saldo_bunga' => null, 'total_saldo_piutang' => null,
            'total_binaan' => null, 'sektor' => collect(), 'kolektibilitas' => collect(),
            'sebaran_provinsi' => collect(), 'tren_kolektibilitas' => ['labels' => [], 'datasets' => []],
            'classification_limited' => false, 'snapshot_revision' => null, 'snapshot_stale' => false,
        ];
    }
}
