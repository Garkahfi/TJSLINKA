<?php

namespace App\Services\Monitoring;

use App\Models\PumkMonitoringReport;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PumkInternalMonitoringService
{
    private const MONTHS = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    /** @return array<string, mixed> */
    public function report(?int $requestedYear = null): array
    {
        if ($requestedYear !== null && ($requestedYear < 1900 || $requestedYear > 2100)) {
            throw new InvalidArgumentException('Tahun laporan tidak valid.');
        }

        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $loans = PumkPinjaman::query()->with(['mitra.sektorUsaha', 'mitra.wilayah', 'saldoAwal', 'angsuran'])->get();
        $reports = PumkMonitoringReport::query()->with('positions')->whereDate('as_of_date', '<=', $today->toDateString())->orderBy('as_of_date')->get();
        $evidence = $this->evidenceDates($loans, $reports, $today);
        $years = $evidence->map(fn (CarbonImmutable $date): int => $date->year)->unique()->sortDesc()->values();
        $latestEvidence = $evidence->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp())->last();

        if ($latestEvidence !== null && $latestEvidence->year < $today->year) {
            $carried = $this->positionsAt($loans, $latestEvidence, $reports);
            if (collect($carried['rows'])->contains(fn (array $row): bool => bccomp($row['total'], '0', 2) > 0)) {
                $years->push($today->year);
                $years = $years->unique()->sortDesc()->values();
            }
        }

        $year = $requestedYear ?? ($years->contains($today->year) ? $today->year : $years->first());
        if ($year === null || $year > $today->year || ! $years->contains($year)) {
            return $this->emptyReport($year, $years->all());
        }

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
        $hasKnownPosition = $rows->isNotEmpty();
        $principal = $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['saldo_pokok'], 2), '0.00');
        $interest = $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['saldo_bunga'], 2), '0.00');
        $total = bcadd($principal, $interest, 2);
        $group = fn (string $field) => $rows->groupBy($field)
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
            'unknown_loans' => $positions['unknown'],
            'negative_loans' => $rows->filter(fn (array $row): bool => bccomp($row['total'], '0', 2) < 0)->count(),
            'payment_count' => $activity->count(),
            'activity_pokok' => $activity->reduce(fn (string $sum, $payment): string => bcadd($sum, (string) $payment->pokok, 2), '0.00'),
            'activity_bunga' => $activity->reduce(fn (string $sum, $payment): string => bcadd($sum, (string) $payment->bunga, 2), '0.00'),
            'saldo_pokok' => $hasKnownPosition ? (float) $principal : null,
            'saldo_bunga' => $hasKnownPosition ? (float) $interest : null,
            'total_saldo_piutang' => $hasKnownPosition ? (float) $total : null,
            'total_binaan' => $hasKnownPosition
                ? $rows->filter(fn (array $row): bool => bccomp($row['total'], '0', 2) > 0)->pluck('mitra_id')->unique()->count()
                : null,
            'sektor' => $group('sektor'),
            'kolektibilitas' => $group('kolektibilitas'),
            'sebaran_provinsi' => $group('provinsi'),
            'tren_kolektibilitas' => $this->trend($year, $yearEnd, $loans, $reports, $evidence),
            'classification_limited' => $rows->contains(fn (array $row): bool => $row['classification_limited']),
            'snapshot_revision' => $reports->first(fn (PumkMonitoringReport $report): bool => $report->as_of_date->toDateString() === $asOf->toDateString())?->revision,
            'snapshot_stale' => (bool) $reports->first(fn (PumkMonitoringReport $report): bool => $report->as_of_date->toDateString() === $asOf->toDateString())?->needs_reconcile,
        ];
    }

    /** @return array{rows:list<array<string,mixed>>,unknown:int,source_updated_at:?string} */
    public function positionForCapture(CarbonImmutable $asOf): array
    {
        $loans = PumkPinjaman::query()->with(['mitra.sektorUsaha', 'mitra.wilayah', 'saldoAwal', 'angsuran'])->get();
        $reports = PumkMonitoringReport::query()->with('positions')
            ->whereDate('as_of_date', '<=', $asOf->toDateString())->orderBy('as_of_date')->get();
        $positions = $this->positionsAt($loans, $asOf, $reports);

        return $positions + ['source_updated_at' => $this->lastSourceUpdate($loans, $asOf, $reports)];
    }

    /**
     * @param  EloquentCollection<int, PumkPinjaman>  $loans
     * @param  EloquentCollection<int, PumkMonitoringReport>  $reports
     * @return array{rows:list<array<string, mixed>>,unknown:int}
     */
    public function positionsAt(EloquentCollection $loans, CarbonImmutable $asOf, EloquentCollection $reports): array
    {
        $snapshot = $reports->last(fn (PumkMonitoringReport $report): bool => $report->as_of_date->toDateString() <= $asOf->toDateString());
        $snapshotPositions = $snapshot?->positions->keyBy('pinjaman_id') ?? collect();
        $rows = [];
        $unknown = 0;

        foreach ($loans as $loan) {
            $start = $this->loanStartDate($loan);
            if ($start === null || $start->greaterThan($asOf)) {
                continue;
            }
            $position = $this->loanBalanceAt($loan, $asOf);
            if ($position === null) {
                $unknown++;

                continue;
            }
            $total = bcadd($position['pokok'], $position['bunga'], 2);
            if ($loan->lunas_at !== null && $this->localDate($loan->lunas_at)->lessThanOrEqualTo($asOf) && bccomp($total, '0', 2) <= 0) {
                continue;
            }

            $recorded = $snapshotPositions->get($loan->id);
            $historicalAttributes = $loan->mitra?->updated_at?->lessThanOrEqualTo($asOf->endOfDay())
                && $loan->updated_at?->lessThanOrEqualTo($asOf->endOfDay());
            $useRecorded = $recorded !== null
                && ($snapshot->as_of_date->toDateString() === $asOf->toDateString() || ! $historicalAttributes);
            $sector = $useRecorded ? $recorded->sektor
                : ($historicalAttributes ? ($loan->mitra?->sektorUsaha?->nama ?? $loan->mitra?->sektor_sumber) : null);
            $region = $useRecorded ? $recorded->wilayah
                : ($historicalAttributes ? ($loan->mitra?->wilayah?->nama ?? $loan->mitra?->wilayah_sumber) : null);
            $quality = $useRecorded ? $recorded->kolektibilitas
                : ($historicalAttributes ? $loan->kolektibilitas : null);
            $rows[] = [
                'pinjaman_id' => $loan->id,
                'mitra_id' => $loan->mitra_id,
                'saldo_pokok' => $position['pokok'],
                'saldo_bunga' => $position['bunga'],
                'total' => $total,
                'sektor' => filled($sector) ? $sector : 'Belum Terverifikasi',
                'wilayah' => filled($region) ? $region : 'Belum Terverifikasi',
                'provinsi' => filled($region) ? $this->provinceForRegion($region) : 'Belum Terverifikasi',
                'kolektibilitas' => filled($quality) ? $quality : 'Belum Dinilai',
                'classification_limited' => ($useRecorded ? $recorded->classification_limited : ! $historicalAttributes)
                    || blank($sector) || blank($region) || blank($quality),
                'source_kind' => $position['source_kind'],
            ];
        }

        return ['rows' => $rows, 'unknown' => $unknown];
    }

    /** @return array{pokok:string,bunga:string,source_kind:string}|null */
    private function loanBalanceAt(PumkPinjaman $loan, CarbonImmutable $asOf): ?array
    {
        if ($loan->source_updated_at !== null) {
            $sourceDate = $this->localDate($loan->source_updated_at);
            $baseline = $loan->baseline_sumber ?? [];
            if ($sourceDate->greaterThan($asOf) || ! isset($baseline['sisa_pokok'], $baseline['sisa_bunga'])) {
                return null;
            }
            $payments = $loan->angsuran->filter(fn ($payment): bool => $payment->batch_id === null
                && $payment->created_at !== null
                && $payment->created_at->greaterThan($loan->source_updated_at)
                && $this->localDate($payment->periode)->lessThanOrEqualTo($asOf));
            $principal = (string) $baseline['sisa_pokok'];
            $interest = (string) $baseline['sisa_bunga'];
            foreach ($payments as $payment) {
                $principal = bcsub($principal, (string) $payment->pokok, 2);
                $interest = bcsub($interest, (string) $payment->bunga, 2);
            }

            return ['pokok' => $principal, 'bunga' => $interest, 'source_kind' => 'baseline_sumber'];
        }

        if ($loan->pinjaman_pokok === null || $loan->pinjaman_bunga === null) {
            return null;
        }
        $opening = $loan->saldoAwal;
        if ($opening !== null && $this->localDate($opening->cutoff_date)->greaterThan($asOf)) {
            return null;
        }
        $principal = bcsub((string) $loan->pinjaman_pokok, (string) ($opening?->pokok_masuk ?? '0.00'), 2);
        $interest = bcsub((string) $loan->pinjaman_bunga, (string) ($opening?->bunga_masuk ?? '0.00'), 2);
        foreach ($loan->angsuran as $payment) {
            if ($payment->periode === null || $this->localDate($payment->periode)->greaterThan($asOf)
                || ($opening !== null && $this->localDate($payment->periode)->lessThanOrEqualTo($this->localDate($opening->cutoff_date)))) {
                continue;
            }
            $principal = bcsub($principal, (string) $payment->pokok, 2);
            $interest = bcsub($interest, (string) $payment->bunga, 2);
        }

        return ['pokok' => $principal, 'bunga' => $interest, 'source_kind' => $opening ? 'saldo_awal' : 'riwayat_rinci'];
    }

    /** @return Collection<int, CarbonImmutable> */
    private function evidenceDates(EloquentCollection $loans, EloquentCollection $reports, CarbonImmutable $today): Collection
    {
        $dates = collect();
        foreach ($loans as $loan) {
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
            $start = $this->loanStartDate($loan);
            if ($start === null || $start->greaterThan($asOf)) {
                continue;
            }
            if ($loan->source_updated_at === null) {
                $timestamps->push($loan->created_at);
            } elseif ($this->localDate($loan->source_updated_at)->lessThanOrEqualTo($asOf)) {
                $timestamps->push($loan->updated_at);
            }
            if ($loan->mitra?->updated_at?->lessThanOrEqualTo($asOf->endOfDay())) {
                $timestamps->push($loan->mitra->updated_at);
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
            if ($positions['rows'] === []) {
                return null;
            }

            return collect($positions['rows'])->groupBy('kolektibilitas')
                ->map(fn (Collection $rows): float => (float) $rows->reduce(
                    fn (string $sum, array $row): string => bcadd($sum, $row['total'], 2), '0.00',
                ))->all();
        });
        $categories = $points->filter()->flatMap(fn (array $point): array => array_keys($point))->unique()->sort()->values();

        return [
            'labels' => $months->map(fn (int $month): string => self::MONTHS[$month].' '.$year)->all(),
            'datasets' => $categories->map(fn (string $category): array => [
                'label' => $category,
                'data' => $points->map(fn (?array $point): ?float => $point[$category] ?? null)->all(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyReport(?int $year, array $years): array
    {
        return [
            'year' => $year, 'years' => $years, 'status' => 'unavailable', 'carried_from_previous_year' => false, 'as_of_date' => null,
            'updated_at' => null, 'known_loans' => 0, 'unknown_loans' => 0, 'negative_loans' => 0,
            'payment_count' => 0, 'activity_pokok' => '0.00', 'activity_bunga' => '0.00',
            'saldo_pokok' => null, 'saldo_bunga' => null, 'total_saldo_piutang' => null,
            'total_binaan' => null, 'sektor' => collect(), 'kolektibilitas' => collect(),
            'sebaran_provinsi' => collect(), 'tren_kolektibilitas' => ['labels' => [], 'datasets' => []],
            'classification_limited' => false, 'snapshot_revision' => null, 'snapshot_stale' => false,
        ];
    }

    private function provinceForRegion(string $region): string
    {
        $name = str($region)->lower()->ascii()->replaceMatches('/^(kab(?:upaten)?|kota)\.?\s+/i', '')->trim()->toString();
        if (in_array($name, ['boyolali', 'karanganyar', 'klaten', 'sragen', 'sukoharjo', 'wonogiri'], true)) {
            return 'Jawa Tengah';
        }
        if (in_array($name, ['blitar', 'jember', 'kediri', 'madiun', 'magetan', 'ngawi', 'pacitan', 'ponorogo', 'trenggalek', 'tulungagung'], true)) {
            return 'Jawa Timur';
        }

        return $region;
    }

    private function localDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), 'Asia/Jakarta')->startOfDay();
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
