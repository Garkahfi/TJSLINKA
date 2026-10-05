<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PumkInternalTrendBuilder
{
    private const MONTHS = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    public function __construct(private readonly PumkInternalPositionService $positions) {}

    /** @return array{labels:list<string>,datasets:list<array{label:string,data:list<?float>}>} */
    public function trend(
        int $year,
        CarbonImmutable $yearEnd,
        EloquentCollection $loans,
        EloquentCollection $reports,
        Collection $evidence,
        ?CarbonImmutable $precomputedDate = null,
        ?array $precomputedPositions = null,
    ): array {
        $months = collect(range(1, 12));
        $points = $months->map(function (int $month) use ($year, $yearEnd, $loans, $reports, $evidence, $precomputedDate, $precomputedPositions): ?array {
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
            $positions = $precomputedDate !== null && $precomputedPositions !== null
                && $date->toDateString() === $precomputedDate->toDateString()
                    ? $precomputedPositions
                    : $this->positions->positionsAt($loans, $date, $reports);
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
}
