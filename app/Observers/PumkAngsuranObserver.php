<?php

namespace App\Observers;

use App\Models\PumkAngsuran;
use App\Models\PumkPinjaman;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Monitoring\PumkClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PumkAngsuranObserver
{
    private static bool $reportTableAvailable = false;

    public function saving(PumkAngsuran $angsuran): void
    {
        $this->captureBaseline($angsuran->pinjaman_id);
        if ($angsuran->isDirty('pinjaman_id') && $angsuran->exists) {
            $this->captureBaseline($angsuran->getOriginal('pinjaman_id'));
        }
    }

    public function deleting(PumkAngsuran $angsuran): void
    {
        $this->captureBaseline($angsuran->pinjaman_id);
    }

    public function saved(PumkAngsuran $angsuran): void
    {
        $this->sync($angsuran->pinjaman_id);
        if ($angsuran->wasChanged('pinjaman_id')) {
            $this->sync($angsuran->getOriginal('pinjaman_id'));
        }
        if ($angsuran->wasRecentlyCreated || $angsuran->wasChanged(['periode', 'pokok', 'bunga', 'pinjaman_id'])) {
            $this->markReportsStale($angsuran, $angsuran->wasChanged('periode') ? $angsuran->getRawOriginal('periode') : null);
        }
    }

    public function deleted(PumkAngsuran $angsuran): void
    {
        $this->sync($angsuran->pinjaman_id);
        $this->markReportsStale($angsuran);
    }

    private function captureBaseline(?int $id): void
    {
        if ($loan = PumkPinjaman::find($id)) {
            app(PiutangCalculator::class)->simpanBaselineSumber($loan);
        }
    }

    private function sync(?int $id): void
    {
        if ($loan = PumkPinjaman::find($id)) {
            $before = $loan->kolektibilitas;
            app(PiutangCalculator::class)->sinkronkanCache($loan);
            if ($before !== $loan->kolektibilitas) {
                app(PumkClassificationService::class)->record(null, $loan, 'kolektibilitas', $loan->kolektibilitas,
                    CarbonImmutable::now('Asia/Jakarta'), 'estimate', (string) Str::uuid(), auth('pumk')->id());
            }
        }
    }

    private function markReportsStale(PumkAngsuran $angsuran, mixed $oldPeriod = null): void
    {
        if (! self::$reportTableAvailable && ! Schema::hasTable('pumk_monitoring_reports')) {
            return;
        }
        self::$reportTableAvailable = true;

        $periods = collect([$oldPeriod, $angsuran->periode])->filter()
            ->map(fn ($period): string => Carbon::parse($period)->toDateString());
        if ($periods->isEmpty()) {
            return;
        }

        DB::table('pumk_monitoring_reports')
            ->whereDate('as_of_date', '>=', $periods->min())
            ->update(['needs_reconcile' => true, 'updated_at' => now()]);
    }
}
