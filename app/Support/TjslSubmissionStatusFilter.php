<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TjslSubmissionStatusFilter
{
    /** @return array<string, string> */
    public static function options(bool $includeDraft): array
    {
        $options = [
            'all' => 'Semua Status Aktif',
            'waiting' => 'Menunggu Pengecekan',
            'approved_phase1' => 'ACC Tahap 1',
            'rejected' => 'Ditolak',
            'completed' => 'Selesai',
        ];

        if ($includeDraft) {
            $options = ['all' => 'Semua Status Aktif', 'draft' => 'Draft'] + array_diff_key($options, ['all' => true]);
        }

        return $options;
    }

    public static function selected(Request $request, bool $includeDraft): string
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_keys(self::options($includeDraft)))],
        ]);

        return $validated['status'] ?? 'all';
    }

    /** @return list<string>|null */
    public static function statuses(string $filter): ?array
    {
        return match ($filter) {
            'draft' => ['draft'],
            'waiting' => ['pending_fase1', 'pending_fase2'],
            'approved_phase1' => ['approved_fase1'],
            'rejected' => ['rejected_fase1'],
            'completed' => ['completed'],
            default => null,
        };
    }

    /** @return list<string> */
    public static function submittedStatuses(): array
    {
        return ['pending_fase1', 'approved_fase1', 'pending_fase2', 'rejected_fase1', 'completed'];
    }

    /** @return list<string> */
    public static function visibleStatuses(string $filter, bool $includeDraft): array
    {
        $statuses = self::statuses($filter);

        if ($statuses !== null) {
            return $statuses;
        }

        return $includeDraft
            ? ['draft', 'pending_fase1', 'approved_fase1', 'pending_fase2', 'completed']
            : ['pending_fase1', 'approved_fase1', 'pending_fase2', 'completed'];
    }

    public static function apply(Builder $query, string $filter, bool $includeDraft): Builder
    {
        return match ($filter) {
            'draft' => $query->where('status', 'draft'),
            'waiting' => $query->whereIn('status', ['pending_fase1', 'pending_fase2']),
            'approved_phase1' => $query
                ->where('status', 'approved_fase1')
                ->whereNull('fase2_rejected_reason'),
            'rejected' => $query->where(function (Builder $statusQuery): void {
                $statusQuery->where('status', 'rejected_fase1')
                    ->orWhere(function (Builder $phaseTwoQuery): void {
                        $phaseTwoQuery->where('status', 'approved_fase1')
                            ->whereNotNull('fase2_rejected_reason');
                    });
            }),
            'completed' => $query->where('status', 'completed'),
            default => $query->whereIn('status', self::visibleStatuses('all', $includeDraft)),
        };
    }
}
