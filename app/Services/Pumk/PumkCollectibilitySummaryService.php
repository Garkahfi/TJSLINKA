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
            ->with(['saldoAwal', 'angsuran'])
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
                    $amounts[$category] = bcadd($amounts[$category], $position['raw_balance'] ?? $amount, 20);
                }
            });

        $rawSubtotal = array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 20), '0.00');

        return [
            'nominal' => array_map(PumkDecimal::roundCents(...), $amounts),
            'subtotal' => PumkDecimal::roundCents($rawSubtotal),
            'unknown_balances' => $unknownBalances,
            'cache_differences' => $cacheDifferences,
            'status_flag_mismatches' => $statusFlagMismatches,
        ];
    }

    /** The audit, filter, and footer use one read-only financial position.
     * @return array{category:string,balance:?string,raw_balance:?string,cache_difference:bool}
     */
    public function positionForLoan(PumkPinjaman $loan): array
    {
        $loan->loadMissing(['saldoAwal', 'angsuran']);
        if (! $this->hasCardComponents($loan)) {
            return ['category' => 'belum_dinilai', 'balance' => null, 'raw_balance' => null, 'cache_difference' => false];
        }
        $calculated = $this->calculator->hitungUntukPinjaman($loan);
        $balance = (string) $calculated['total_sisa'];
        $cached = $loan->total_sisa === null ? null : (string) $loan->total_sisa;

        return [
            'category' => self::normalizeCategory($calculated['kolektibilitas']),
            'balance' => $balance,
            'raw_balance' => (string) $calculated['total_sisa_raw'],
            'cache_difference' => $cached === null || bccomp($cached, $balance, 2) !== 0,
        ];
    }

    /** Match the table to the same financial category policy as the footer.
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
            ->with(['saldoAwal', 'angsuran'])
            ->chunkById(200, function ($loans) use ($category, &$ids): void {
                foreach ($loans as $loan) {
                    if ($this->positionForLoan($loan)['category'] === $category) {
                        $ids[] = $loan->id;
                    }
                }
            });

        return $ids;
    }

    /**
     * The category-filtered list needs matching IDs and a footer for the same
     * scope. Calculate each loan only once during this read-only operation.
     *
     * @param  array<string, mixed>  $filters
     * @return array{ids:list<int>,summary:array{nominal:array<string,string>,subtotal:string,unknown_balances:int,cache_differences:int,status_flag_mismatches:int}}
     */
    public function matchingLoanIdsWithSummary(array $filters, string $category): array
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Kategori kolektibilitas tidak dikenal.');
        }

        $ids = [];
        $amounts = array_fill_keys(self::CATEGORIES, '0.00');
        $unknownBalances = 0;
        $cacheDifferences = 0;
        $this->recapScope($this->ownerScope($filters))
            ->with(['saldoAwal', 'angsuran'])
            ->chunkById(200, function ($loans) use ($category, &$ids, &$amounts, &$unknownBalances, &$cacheDifferences): void {
                foreach ($loans as $loan) {
                    $position = $this->positionForLoan($loan);
                    if ($position['category'] !== $category) {
                        continue;
                    }
                    $ids[] = $loan->id;
                    $amount = $position['balance'];
                    $cacheDifferences += (int) $position['cache_difference'];
                    if ($amount === null) {
                        $unknownBalances++;

                        continue;
                    }
                    $amounts[$category] = bcadd($amounts[$category], $position['raw_balance'] ?? $amount, 20);
                }
            });

        return [
            'ids' => $ids,
            'summary' => [
                'nominal' => array_map(PumkDecimal::roundCents(...), $amounts),
                'subtotal' => PumkDecimal::roundCents(array_reduce(
                    $amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 20), '0.00',
                )),
                'unknown_balances' => $unknownBalances,
                'cache_differences' => $cacheDifferences,
                'status_flag_mismatches' => 0,
            ],
        ];
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

    private function hasCardComponents(PumkPinjaman $loan): bool
    {
        $baseline = $loan->baseline_sumber ?? ['sisa_pokok' => $loan->sisa_pokok, 'sisa_bunga' => $loan->sisa_bunga];

        return (($loan->source_updated_at !== null && isset($baseline['sisa_pokok'])) || $loan->pinjaman_pokok !== null)
            && (($loan->source_updated_at !== null && isset($baseline['sisa_bunga'])) || $loan->pinjaman_bunga !== null);
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
