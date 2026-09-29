<?php

namespace App\Services\Monitoring;

use App\Models\PumkClassificationHistory;
use App\Models\PumkMitra;
use App\Models\PumkMonitoringPosition;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PumkClassificationService
{
    /** @return array{value:?string,source_kind:string,reason_code:?string,limited:bool,effective_date:?string} */
    public function resolve(
        PumkPinjaman $loan,
        string $attribute,
        CarbonImmutable $asOf,
        ?PumkMonitoringPosition $snapshot = null,
        ?CarbonImmutable $snapshotDate = null,
    ): array {
        if (! in_array($attribute, ['sektor', 'wilayah', 'kolektibilitas'], true)) {
            throw new InvalidArgumentException('Atribut klasifikasi PUMK tidak dikenal.');
        }
        $owner = $attribute === 'kolektibilitas' ? $loan : $loan->mitra;
        $history = $owner?->classificationHistory?->filter(fn (PumkClassificationHistory $item): bool => $item->attribute === $attribute && $item->effective_from->toDateString() <= $asOf->toDateString())
            ->sort(function (PumkClassificationHistory $left, PumkClassificationHistory $right): int {
                return [$right->effective_from->toDateString(), $right->recorded_at?->getTimestamp(), $right->id]
                    <=> [$left->effective_from->toDateString(), $left->recorded_at?->getTimestamp(), $left->id];
            })->first();
        if ($history !== null) {
            return [
                'value' => $history->value,
                'source_kind' => $history->source_kind,
                'reason_code' => filled($history->value) ? null : 'classification_missing',
                'limited' => blank($history->value) || $history->source_kind === 'estimate',
                'effective_date' => $history->effective_from->toDateString(),
            ];
        }

        $snapshotValue = $snapshot?->getAttribute($attribute);
        if ($snapshotDate !== null && $snapshotDate->lessThanOrEqualTo($asOf) && filled($snapshotValue)) {
            return [
                'value' => (string) $snapshotValue, 'source_kind' => 'snapshot',
                'reason_code' => null, 'limited' => (bool) $snapshot->classification_limited,
                'effective_date' => $snapshotDate->toDateString(),
            ];
        }

        $current = match ($attribute) {
            'sektor' => $loan->mitra?->sektorUsaha?->nama ?? $loan->mitra?->sektor_sumber,
            'wilayah' => $loan->mitra?->wilayah?->nama ?? $loan->mitra?->wilayah_sumber,
            'kolektibilitas' => $loan->kolektibilitas,
        };
        if (blank($current)) {
            return ['value' => null, 'source_kind' => 'missing', 'reason_code' => 'classification_missing', 'limited' => true, 'effective_date' => null];
        }
        if ($owner?->updated_at?->lessThanOrEqualTo($asOf->endOfDay())) {
            return ['value' => (string) $current, 'source_kind' => 'current_record', 'reason_code' => null, 'limited' => false,
                'effective_date' => $owner->updated_at->timezone('Asia/Jakarta')->toDateString()];
        }

        return ['value' => null, 'source_kind' => 'not_effective', 'reason_code' => 'classification_not_effective', 'limited' => true,
            'effective_date' => $owner?->updated_at?->timezone('Asia/Jakarta')->toDateString()];
    }

    public function record(
        ?PumkMitra $mitra,
        ?PumkPinjaman $loan,
        string $attribute,
        ?string $value,
        CarbonImmutable $effectiveFrom,
        string $sourceKind,
        ?string $sourceRef = null,
        ?int $actorId = null,
    ): PumkClassificationHistory {
        $isLoanAttribute = $attribute === 'kolektibilitas';
        if (! in_array($attribute, ['sektor', 'wilayah', 'kolektibilitas'], true)
            || ($isLoanAttribute && ($loan === null || $mitra !== null))
            || (! $isLoanAttribute && ($mitra === null || $loan !== null))
            || ! in_array($sourceKind, ['import', 'admin', 'estimate', 'verified_correction'], true)
            || ($sourceKind === 'verified_correction' && blank($sourceRef))) {
            throw new InvalidArgumentException('Pemilik atau atribut riwayat klasifikasi PUMK tidak valid.');
        }
        $value = filled($value) ? trim($value) : null;
        $date = $effectiveFrom->setTimezone('Asia/Jakarta')->toDateString();
        $fingerprint = hash('sha256', json_encode([
            $mitra?->id, $loan?->id, $attribute, $value, $date, $sourceKind, $sourceRef,
        ], JSON_THROW_ON_ERROR));
        $history = PumkClassificationHistory::firstOrCreate(['fingerprint' => $fingerprint], [
            'mitra_id' => $mitra?->id, 'pinjaman_id' => $loan?->id,
            'attribute' => $attribute, 'value' => $value,
            'effective_from' => $date, 'recorded_at' => now(),
            'source_kind' => $sourceKind, 'source_ref' => $sourceRef,
            'recorded_by' => $actorId,
        ]);
        if ($history->wasRecentlyCreated) {
            DB::table('pumk_monitoring_reports')->whereDate('as_of_date', '>=', $date)
                ->update(['needs_reconcile' => true, 'updated_at' => now()]);
        }

        return $history;
    }
}
