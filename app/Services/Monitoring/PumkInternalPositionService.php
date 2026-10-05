<?php

namespace App\Services\Monitoring;

use App\Models\PumkMonitoringReport;
use App\Models\PumkPinjaman;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkLoanBalanceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PumkInternalPositionService
{
    public function __construct(
        private readonly PumkLoanBalanceResolver $balances,
        private readonly PumkClassificationService $classifications,
        private readonly PiutangCalculator $calculator,
        private readonly PumkInternalTimeline $timeline,
    ) {}

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
            $start = $this->timeline->loanStartDate($loan);
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
            $snapshotDate = $snapshot ? $this->timeline->localDate($snapshot->as_of_date) : null;
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
}
