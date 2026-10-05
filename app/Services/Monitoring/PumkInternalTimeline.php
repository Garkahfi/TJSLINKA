<?php

namespace App\Services\Monitoring;

use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PumkInternalTimeline
{
    /** @return Collection<int, CarbonImmutable> */
    public function evidenceDates(EloquentCollection $loans, EloquentCollection $reports, CarbonImmutable $today): Collection
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

    public function lastSourceUpdate(EloquentCollection $loans, CarbonImmutable $asOf, EloquentCollection $reports): ?string
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

    public function localDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), 'Asia/Jakarta')->startOfDay();
    }

    private function completionDate(CarbonInterface $timestamp): CarbonImmutable
    {
        return CarbonImmutable::instance($timestamp)->setTimezone('Asia/Jakarta')->startOfDay();
    }

    public function loanStartDate(PumkPinjaman $loan): ?CarbonImmutable
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
