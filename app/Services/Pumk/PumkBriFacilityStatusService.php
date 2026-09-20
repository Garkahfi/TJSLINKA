<?php

namespace App\Services\Pumk;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Facades\DB;

class PumkBriFacilityStatusService
{
    public function sync(): void
    {
        $latestComplete = DB::table('pumk_bri_snapshot_bulanan')
            ->selectRaw('(tahun * 100) + bulan as period_key')
            ->groupBy('tahun', 'bulan')
            ->havingRaw('SUM(CASE WHEN profil_sumber_terverifikasi = 0 THEN 1 ELSE 0 END) = 0')
            ->orderByDesc('period_key')
            ->value('period_key');

        if ($latestComplete === null) {
            return;
        }

        PumkBriFasilitas::query()->with(['snapshots' => fn ($query) => $query
            ->orderBy('tahun')->orderBy('bulan')])->chunkById(200, function ($facilities) use ($latestComplete): void {
                foreach ($facilities as $facility) {
                    $snapshots = $facility->snapshots;
                    if ($snapshots->isEmpty()) {
                        continue;
                    }

                    /** @var PumkBriSnapshotBulanan $first */
                    $first = $snapshots->first();
                    /** @var PumkBriSnapshotBulanan $last */
                    $last = $snapshots->last();
                    $lastPeriod = ($last->tahun * 100) + $last->bulan;
                    $facility->forceFill([
                        'sektor_usaha' => $snapshots->first(
                            static fn (PumkBriSnapshotBulanan $snapshot): bool => filled($snapshot->sektor_usaha_sumber),
                        )?->sektor_usaha_sumber,
                        'status' => (float) $last->saldo_piutang <= 0 || $lastPeriod < (int) $latestComplete
                            ? PumkBriFasilitas::STATUS_LUNAS
                            : PumkBriFasilitas::STATUS_AKTIF,
                        'first_seen_period' => sprintf('%04d-%02d-01', $first->tahun, $first->bulan),
                        'last_seen_period' => sprintf('%04d-%02d-01', $last->tahun, $last->bulan),
                    ])->save();
                }
            });
    }
}
