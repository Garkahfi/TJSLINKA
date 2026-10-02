<?php

namespace App\Services\Monitoring;

use App\Models\PumkMonitoringReport;
use App\Models\PumkPinjaman;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkLoanBalanceResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PumkInternalMonitoringService
{
    public const MIN_REPORT_YEAR = 2025;

    public function __construct(
        private readonly PumkLoanBalanceResolver $balances,
        private readonly PumkClassificationService $classifications,
        private readonly PiutangCalculator $calculator,
    ) {}

    private const MONTHS = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

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
        $evidence = $this->evidenceDates($loans, $reports, $today);
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
        $updatedAt = $this->lastSourceUpdate($loans, $asOf, $reports);
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
            'tren_kolektibilitas' => $this->trend($year, $yearEnd, $loans, $reports, $evidence),
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

        return $positions + ['source_updated_at' => $this->lastSourceUpdate($loans, $asOf, $reports)];
    }

    /** @return array{report:array<string,mixed>,items:list<array<string,mixed>>} */
    public function diagnostics(?int $year = null): array
    {
        $report = $this->report($year);
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
        $positions = $this->positionsAt($loans, $asOf, $reports);
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
                || $this->loanStartDate($loan) === null) {
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

    /**
     * @param  EloquentCollection<int, PumkPinjaman>  $loans
     * @param  EloquentCollection<int, PumkMonitoringReport>  $reports
     * @return array{rows:list<array<string, mixed>>,unknown:int,closed:int,closed_ids:list<int>,unknown_details:list<array<string,mixed>>}
     */
    public function positionsAt(EloquentCollection $loans, CarbonImmutable $asOf, EloquentCollection $reports): array
    {
        $snapshot = $reports->last(fn (PumkMonitoringReport $report): bool => $report->as_of_date->toDateString() <= $asOf->toDateString());
        $snapshotPositions = $snapshot?->positions->keyBy('pinjaman_id') ?? collect();
        $rows = [];
        $unknown = 0;
        $closed = 0;
        $closedIds = [];
        $unknownDetails = [];

        foreach ($loans as $loan) {
            if ($loan->isUnfundedVoid()) {
                continue;
            }
            $start = $this->loanStartDate($loan);
            if ($start === null || $start->greaterThan($asOf)) {
                continue;
            }
            if ($loan->isClosedAt($asOf)) {
                $closed++;
                $closedIds[] = $loan->id;

                continue;
            }
            $position = $this->balances->resolve($loan, $asOf);
            if (! $position['known']) {
                $unknown++;
                $unknownDetails[] = [
                    'pinjaman_id' => $loan->id, 'mitra_id' => $loan->mitra_id,
                    'reason_code' => $position['reason_code'], 'source_kind' => $position['source_kind'],
                ];

                continue;
            }
            $total = $position['total'];

            $recorded = $snapshotPositions->get($loan->id);
            $snapshotDate = $snapshot ? $this->localDate($snapshot->as_of_date) : null;
            $sector = $this->classifications->resolve($loan, 'sektor', $asOf, $recorded, $snapshotDate);
            $region = $this->classifications->resolve($loan, 'wilayah', $asOf, $recorded, $snapshotDate);
            $quality = $this->classifications->resolve($loan, 'kolektibilitas', $asOf, $recorded, $snapshotDate);
            if ($loan->source_updated_at !== null
                && $loan->source_updated_at->toDateString() <= $asOf->toDateString()) {
                $calculated = $this->calculator->hitungUntukPinjaman($loan, $asOf);
                $value = $calculated['kolektibilitas'];
                $hasRawSourceFormula = isset($loan->baseline_sumber['formula_sumber']);
                $quality = [
                    'value' => $value === null ? null : match ($value) {
                        'lancar' => 'Lancar',
                        'kurang_lancar' => 'Kurang Lancar',
                        'diragukan' => 'Diragukan',
                        'macet' => 'Macet',
                    },
                    'source_kind' => 'calculated',
                    'reason_code' => $value === null ? 'schedule_missing'
                        : ($hasRawSourceFormula ? null : 'source_formula_precision_pending'),
                    'limited' => $value === null || ! $hasRawSourceFormula,
                    'effective_date' => $asOf->toDateString(),
                ];
            }
            $province = filled($region['value']) ? $this->provinceForRegion($region['value']) : null;
            $rows[] = [
                'pinjaman_id' => $loan->id,
                'mitra_id' => $loan->mitra_id,
                'saldo_pokok' => $position['saldo_pokok'],
                'saldo_bunga' => $position['saldo_bunga'],
                'total' => $total,
                'sektor' => $sector['value'] ?? 'Belum Terverifikasi',
                'wilayah' => $region['value'] ?? 'Belum Terverifikasi',
                'provinsi' => $province ?? (filled($region['value']) ? 'Provinsi Belum Dipetakan' : 'Belum Terverifikasi'),
                'kolektibilitas' => $quality['value'] ?? 'Belum Dinilai',
                'classification_limited' => $sector['limited'] || $region['limited'] || $quality['limited'] || $province === null,
                'classification_sources' => [
                    'sektor' => $sector['source_kind'], 'wilayah' => $region['source_kind'],
                    'kolektibilitas' => $quality['source_kind'],
                ],
                'classification_dates' => [
                    'sektor' => $sector['effective_date'], 'wilayah' => $region['effective_date'],
                    'kolektibilitas' => $quality['effective_date'],
                ],
                'classification_reasons' => [
                    'sektor' => $sector['reason_code'], 'wilayah' => $region['reason_code'],
                    'kolektibilitas' => $quality['reason_code'],
                    'provinsi' => filled($region['value']) && $province === null ? 'province_unmapped' : null,
                ],
                'source_kind' => $position['source_kind'],
            ];
        }

        return ['rows' => $rows, 'unknown' => $unknown, 'closed' => $closed,
            'closed_ids' => $closedIds, 'unknown_details' => $unknownDetails];
    }

    /** @return Collection<int, CarbonImmutable> */
    private function evidenceDates(EloquentCollection $loans, EloquentCollection $reports, CarbonImmutable $today): Collection
    {
        $dates = collect();
        foreach ($loans as $loan) {
            if ($loan->isUnfundedVoid()) {
                continue;
            }
            $origin = $loan->tanggal_pencairan ?? ($loan->source_updated_at === null ? $loan->created_at : null);
            if ($origin !== null) {
                $dates->push($this->localDate($origin));
            }
            if ($loan->source_updated_at !== null) {
                $dates->push($this->localDate($loan->source_updated_at));
            }
            if ($loan->saldoAwal?->cutoff_date !== null) {
                $dates->push($this->localDate($loan->saldoAwal->cutoff_date));
            }
            if ($loan->status === PumkPinjaman::STATUS_LUNAS && $loan->lunas_at !== null) {
                $dates->push($this->completionDate($loan->lunas_at));
            }
            foreach ($loan->closures as $closure) {
                $dates->push($this->completionDate($closure->closed_at));
                if ($closure->reopened_at !== null) {
                    $dates->push($this->completionDate($closure->reopened_at));
                }
            }
            foreach ($loan->classificationHistory as $category) {
                $dates->push($this->localDate($category->effective_from));
            }
            foreach ($loan->mitra?->classificationHistory ?? [] as $category) {
                $dates->push($this->localDate($category->effective_from));
            }
            foreach ($loan->angsuran as $payment) {
                if ($payment->periode !== null) {
                    $periodStart = $this->localDate($payment->periode)->startOfMonth();
                    if ($periodStart->lessThanOrEqualTo($today)) {
                        $dates->push($periodStart->endOfMonth()->startOfDay()->min($today));
                    }
                }
            }
        }
        foreach ($reports as $report) {
            $dates->push($this->localDate($report->as_of_date));
        }

        return $dates->filter(fn (CarbonImmutable $date): bool => $date->lessThanOrEqualTo($today))->unique(fn (CarbonImmutable $date): string => $date->toDateString())->values();
    }

    private function lastSourceUpdate(EloquentCollection $loans, CarbonImmutable $asOf, EloquentCollection $reports): ?string
    {
        $timestamps = collect();
        foreach ($loans as $loan) {
            if ($loan->isUnfundedVoid()) {
                continue;
            }
            $start = $this->loanStartDate($loan);
            if ($start === null || $start->greaterThan($asOf)) {
                continue;
            }
            if ($loan->source_updated_at === null) {
                $timestamps->push($loan->created_at);
            } elseif ($this->localDate($loan->source_updated_at)->lessThanOrEqualTo($asOf)) {
                $timestamps->push($loan->updated_at);
            }
            if ($loan->status === PumkPinjaman::STATUS_LUNAS && $loan->lunas_at !== null
                && $this->completionDate($loan->lunas_at)->lessThanOrEqualTo($asOf)) {
                $timestamps->push($loan->lunas_at);
            }
            if ($loan->mitra?->updated_at?->lessThanOrEqualTo($asOf->endOfDay())) {
                $timestamps->push($loan->mitra->updated_at);
            }
            foreach ($loan->closures as $closure) {
                if ($this->completionDate($closure->closed_at)->lessThanOrEqualTo($asOf)) {
                    $timestamps->push($closure->closed_at);
                }
                if ($closure->reopened_at !== null && $this->completionDate($closure->reopened_at)->lessThanOrEqualTo($asOf)) {
                    $timestamps->push($closure->reopened_at);
                }
            }
            foreach ($loan->classificationHistory as $category) {
                if ($this->localDate($category->effective_from)->lessThanOrEqualTo($asOf)) {
                    $timestamps->push($category->recorded_at);
                }
            }
            foreach ($loan->mitra?->classificationHistory ?? [] as $category) {
                if ($this->localDate($category->effective_from)->lessThanOrEqualTo($asOf)) {
                    $timestamps->push($category->recorded_at);
                }
            }
            if ($loan->saldoAwal?->cutoff_date !== null && $this->localDate($loan->saldoAwal->cutoff_date)->lessThanOrEqualTo($asOf)) {
                $timestamps->push($loan->saldoAwal->updated_at);
            }
            foreach ($loan->angsuran as $payment) {
                if ($payment->periode !== null && $this->localDate($payment->periode)->lessThanOrEqualTo($asOf)) {
                    $timestamps->push($payment->updated_at);
                }
            }
        }
        foreach ($reports as $report) {
            if ($report->as_of_date->lessThanOrEqualTo($asOf)) {
                $timestamps->push($report->source_updated_at);
            }
        }

        return $timestamps->filter()->map(fn ($timestamp): CarbonImmutable => CarbonImmutable::instance($timestamp))
            ->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->last()?->toIso8601String();
    }

    /** @return array{labels:list<string>,datasets:list<array{label:string,data:list<?float>}>} */
    private function trend(int $year, CarbonImmutable $yearEnd, EloquentCollection $loans, EloquentCollection $reports, Collection $evidence): array
    {
        $months = collect(range(1, 12));
        $points = $months->map(function (int $month) use ($year, $yearEnd, $loans, $reports, $evidence): ?array {
            $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta');
            if ($start->greaterThan($yearEnd)) {
                return null;
            }
            $end = $start->endOfMonth()->startOfDay()->min($yearEnd);
            $date = $evidence->filter(fn (CarbonImmutable $item): bool => $item->betweenIncluded($start, $end))
                ->sortBy(fn (CarbonImmutable $item): int => $item->getTimestamp())->last();
            if ($date === null) {
                return null;
            }
            $positions = $this->positionsAt($loans, $date, $reports);
            if ($positions['rows'] === [] && $positions['closed'] === 0) {
                return null;
            }

            return collect($positions['rows'])->filter(fn (array $row): bool => bccomp($row['total'], '0', 2) > 0)
                ->groupBy('kolektibilitas')
                ->map(fn (Collection $rows): float => (float) $rows->reduce(
                    fn (string $sum, array $row): string => bcadd($sum, $row['total'], 2), '0.00',
                ))->all();
        });
        $categories = $points->filter()->flatMap(fn (array $point): array => array_keys($point))->unique()->sort()->values();

        return [
            'labels' => $months->map(fn (int $month): string => self::MONTHS[$month].' '.$year)->all(),
            'datasets' => $categories->map(fn (string $category): array => [
                'label' => $category,
                'data' => $points->map(fn (?array $point): ?float => $point === null ? null : ($point[$category] ?? 0.0))->all(),
            ])->all(),
        ];
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

    private function provinceForRegion(string $region): ?string
    {
        $name = str($region)->lower()->ascii()->replaceMatches('/^(kab(?:upaten)?|kota)\.?\s+/i', '')->trim()->toString();
        if (in_array($name, ['boyolali', 'karanganyar', 'klaten', 'sragen', 'sukoharjo', 'wonogiri'], true)) {
            return 'Jawa Tengah';
        }
        if (in_array($name, ['blitar', 'jember', 'kediri', 'madiun', 'magetan', 'ngawi', 'pacitan', 'ponorogo', 'trenggalek', 'tulungagung'], true)) {
            return 'Jawa Timur';
        }

        return null;
    }

    private function localDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), 'Asia/Jakarta')->startOfDay();
    }

    private function completionDate(CarbonInterface $timestamp): CarbonImmutable
    {
        return CarbonImmutable::instance($timestamp)->setTimezone('Asia/Jakarta')->startOfDay();
    }

    private function loanStartDate(PumkPinjaman $loan): ?CarbonImmutable
    {
        if ($loan->tanggal_pencairan !== null) {
            return $this->localDate($loan->tanggal_pencairan);
        }
        if ($loan->source_updated_at !== null) {
            return $this->localDate($loan->source_updated_at);
        }

        return collect([$loan->saldoAwal?->cutoff_date, $loan->created_at])
            ->merge($loan->angsuran->pluck('periode'))
            ->filter()
            ->map(fn (CarbonInterface $date): CarbonImmutable => $this->localDate($date))
            ->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())
            ->first();
    }
}
