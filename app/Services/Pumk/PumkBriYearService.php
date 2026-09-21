<?php

namespace App\Services\Pumk;

use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Collection;

final class PumkBriYearService
{
    /** @return Collection<int, int> */
    public function availableYears(): Collection
    {
        return PumkBriSnapshotBulanan::query()->distinct()->pluck('tahun')
            ->merge(PumkBriRkaTahunan::query()->pluck('tahun'))
            ->merge(PumkBriPenyaluranBulanan::query()->pluck('tahun'))
            ->map(fn (mixed $year): int => (int) $year)
            ->filter(fn (int $year): bool => $year >= 1900 && $year <= 2100)
            ->unique()
            ->sortDesc()
            ->values();
    }

    public function exists(int $year): bool
    {
        return $this->availableYears()->contains($year);
    }
}
