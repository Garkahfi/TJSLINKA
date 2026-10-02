<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PumkCollectibilitySummaryService
{
    private const CATEGORIES = ['lancar', 'kurang_lancar', 'diragukan', 'macet', 'belum_dinilai'];

    public function __construct(private readonly PiutangCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $filters  Validated list filters; status does not limit this summary.
     * @return array{nominal:array<string,string>,subtotal:string,unknown_balances:int,cache_differences:int,status_flag_mismatches:int}
     */
    public function summarize(array $filters, ?string $selectedCategory = null): array
    {
        if ($selectedCategory !== null && ! in_array($selectedCategory, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Kategori kolektibilitas tidak dikenal.');
        }

        $amounts = array_fill_keys(self::CATEGORIES, '0.00');
        $unknownBalances = 0;
        $cacheDifferences = 0;
        $scope = $this->ownerScope($filters);
        $statusFlagMismatches = $selectedCategory === null
            ? (clone $scope)
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $active) => $active->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', false))
                    ->orWhere(fn (Builder $inactive) => $inactive->where('status', '!=', PumkPinjaman::STATUS_AKTIF)->where('is_active', true)))
                ->count()
            : 0;

        $this->recapScope($scope)
            ->with(['saldoAwal', 'angsuran', 'closures', 'classificationHistory'])
            ->chunkById(200, function ($loans) use ($selectedCategory, &$amounts, &$unknownBalances, &$cacheDifferences): void {
                foreach ($loans as $loan) {
                    $position = $this->positionForLoan($loan);
                    $category = $position['category'];
                    if ($selectedCategory !== null && $category !== $selectedCategory) {
                        continue;
                    }
                    $amount = $position['balance'];
                    $cacheDifferences += (int) $position['cache_difference'];
                    if ($amount === null) {
                        $unknownBalances++;

                        continue;
                    }
                    $amounts[$category] = bcadd($amounts[$category], $amount, 2);
                }
            });

        return [
            'nominal' => $amounts,
            'subtotal' => array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00'),
            'unknown_balances' => $unknownBalances,
            'cache_differences' => $cacheDifferences,
            'status_flag_mismatches' => $statusFlagMismatches,
        ];
    }

    /** The audit and footer must use the same read-only financial position.
     * @return array{category:string,balance:?string,cache_difference:bool}
     */
    public function positionForLoan(PumkPinjaman $loan): array
    {
        $loan->loadMissing(['saldoAwal', 'angsuran', 'closures', 'classificationHistory']);
        $differences = 0;

        return [
            'category' => $this->categoryAtCurrentStatus($loan),
            'balance' => $loan->status === PumkPinjaman::STATUS_LUNAS
                ? $this->closedBalance($loan)
                : $this->activeBalance($loan, $differences),
            'cache_difference' => $differences > 0,
        ];
    }

    /** Match the table to the same category policy, including closure history.
     * @param  array<string,mixed>  $filters
     * @return list<int>
     */
    public function matchingLoanIds(array $filters, string $category): array
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Kategori kolektibilitas tidak dikenal.');
        }
        $ids = [];
        $this->recapScope($this->ownerScope($filters))
            ->with(['saldoAwal', 'angsuran', 'closures', 'classificationHistory'])
            ->chunkById(200, function ($loans) use ($category, &$ids): void {
                foreach ($loans as $loan) {
                    if ($this->positionForLoan($loan)['category'] === $category) {
                        $ids[] = $loan->id;
                    }
                }
            });

        return $ids;
    }

    /** @param array<string, mixed> $filters */
    private function ownerScope(array $filters): Builder
    {
        return PumkPinjaman::query()->whereHas('mitra', function (Builder $query) use ($filters): void {
            if (filled($filters['q'] ?? null)) {
                $query->where('nama_mitra', 'like', '%'.trim((string) $filters['q']).'%');
            }
            if (filled($filters['wilayah'] ?? null)) {
                $query->where('wilayah_id', $filters['wilayah']);
            }
            if (filled($filters['sektor'] ?? null)) {
                $query->where('sektor_usaha_id', $filters['sektor']);
            }
        });
    }

    private function recapScope(Builder $scope): Builder
    {
        $excluded = config('pumk.recap_excluded_source_keys', []);
        if ($excluded !== []) {
            $scope->whereNotIn('source_key', $excluded);
        }

        return $scope->where(fn (Builder $query) => $query
            ->where(fn (Builder $active) => $active->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true))
            ->orWhere('status', PumkPinjaman::STATUS_LUNAS));
    }

    public function isIncluded(PumkPinjaman $loan): bool
    {
        return (($loan->status === PumkPinjaman::STATUS_AKTIF && $loan->is_active)
            || $loan->status === PumkPinjaman::STATUS_LUNAS)
            && ! in_array($loan->source_key, config('pumk.recap_excluded_source_keys', []), true);
    }

    private function activeBalance(PumkPinjaman $loan, int &$cacheDifferences): ?string
    {
        $cached = $loan->total_sisa === null ? null : (string) $loan->total_sisa;
        if (! $this->hasCardComponents($loan)) {
            return $cached;
        }

        $calculated = (string) $this->calculator->hitungUntukPinjaman($loan)['total_sisa'];
        if ($cached === null || bccomp($cached, $calculated, 2) !== 0) {
            $cacheDifferences++;
        }

        return $calculated;
    }

    private function closedBalance(PumkPinjaman $loan): ?string
    {
        $currentClosure = $loan->closures->whereNull('reopened_at')->sortByDesc('id')->first();
        $fromClosure = $currentClosure?->settlement_snapshot['lunas_total_saldo'] ?? null;
        $fromLoan = $loan->lunas_total_saldo;
        if ($fromClosure !== null && $fromLoan !== null && bccomp((string) $fromClosure, (string) $fromLoan, 2) !== 0) {
            return null;
        }
        if ($fromClosure !== null || $fromLoan !== null) {
            return (string) ($fromClosure ?? $fromLoan);
        }

        // A legacy zero can be a reset rather than the closing balance; without metadata it is not proven.
        if ($loan->lunas_at === null || $loan->total_sisa === null || bccomp((string) $loan->total_sisa, '0.00', 2) === 0
            || ! $this->hasCardComponents($loan)) {
            return null;
        }

        $calculated = (string) $this->calculator->hitungUntukPinjaman($loan)['total_sisa'];
        if (bccomp((string) $loan->total_sisa, $calculated, 2) !== 0) {
            return null;
        }

        return (string) $loan->total_sisa;
    }

    private function hasCardComponents(PumkPinjaman $loan): bool
    {
        $baseline = $loan->baseline_sumber ?? ['sisa_pokok' => $loan->sisa_pokok, 'sisa_bunga' => $loan->sisa_bunga];

        return (($loan->source_updated_at !== null && isset($baseline['sisa_pokok'])) || $loan->pinjaman_pokok !== null)
            && (($loan->source_updated_at !== null && isset($baseline['sisa_bunga'])) || $loan->pinjaman_bunga !== null);
    }

    private function categoryAtCurrentStatus(PumkPinjaman $loan): string
    {
        if ($loan->status !== PumkPinjaman::STATUS_LUNAS) {
            return self::normalizeCategory($this->calculator->hitungUntukPinjaman($loan)['kolektibilitas']);
        }

        $value = $loan->kolektibilitas;
        if ($loan->status === PumkPinjaman::STATUS_LUNAS && $loan->lunas_at !== null) {
            $closure = $loan->closures->whereNull('reopened_at')->sortByDesc('id')->first();
            if ($closure !== null && array_key_exists('kolektibilitas', $closure->settlement_snapshot ?? [])) {
                return self::normalizeCategory($closure->settlement_snapshot['kolektibilitas']);
            }
            $closedDate = $loan->lunas_at->timezone('Asia/Jakarta')->toDateString();
            $history = $loan->classificationHistory->where('attribute', 'kolektibilitas');
            if ($history->contains(fn ($item): bool => $item->effective_from->toDateString() > $closedDate)) {
                $value = $history->filter(fn ($item): bool => $item->effective_from->toDateString() <= $closedDate)
                    ->sortByDesc(fn ($item): string => $item->effective_from->toDateString().'-'.str_pad((string) $item->id, 12, '0', STR_PAD_LEFT))
                    ->first()?->value;
            }
        }

        return self::normalizeCategory($value);
    }

    public static function normalizeCategory(?string $value): string
    {
        return match (str_replace([' ', '-'], '_', strtolower(trim((string) $value)))) {
            'l', 'lancar' => 'lancar',
            'kl', 'kurang_lancar' => 'kurang_lancar',
            'd', 'diragukan' => 'diragukan',
            'm', 'macet' => 'macet',
            default => 'belum_dinilai',
        };
    }
}
