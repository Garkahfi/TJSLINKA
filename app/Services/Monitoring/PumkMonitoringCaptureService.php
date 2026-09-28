<?php

namespace App\Services\Monitoring;

use App\Models\PumkMonitoringPosition;
use App\Models\PumkMonitoringReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PumkMonitoringCaptureService
{
    public function __construct(private readonly PumkInternalMonitoringService $monitoring) {}

    /** @return array{status:string,revision:int,known:int,unknown:int} */
    public function capture(CarbonImmutable $asOf): array
    {
        $asOf = $asOf->setTimezone('Asia/Jakarta')->startOfDay();
        if ($asOf->greaterThan(CarbonImmutable::today('Asia/Jakarta'))) {
            throw new InvalidArgumentException('Tanggal posisi tidak boleh berada di masa depan.');
        }

        return DB::transaction(function () use ($asOf): array {
            $date = $asOf->toDateString();
            DB::table('pumk_monitoring_reports')->insertOrIgnore([
                'as_of_date' => $date,
                'source_hash' => str_repeat('0', 64),
                'revision' => 0,
                'generated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $report = PumkMonitoringReport::query()->whereDate('as_of_date', $date)->lockForUpdate()->firstOrFail();
            $position = $this->monitoring->positionForCapture($asOf);
            $rows = collect($position['rows'])->sortBy('pinjaman_id')->values();
            if ($rows->isEmpty() && $position['unknown'] === 0) {
                throw new InvalidArgumentException('Belum ada posisi pinjaman yang dapat dicatat untuk tanggal ini.');
            }
            $hash = hash('sha256', json_encode([
                $rows->map(fn (array $row): array => [
                    $row['pinjaman_id'], $row['mitra_id'], $row['saldo_pokok'], $row['saldo_bunga'],
                    $row['sektor'], $row['wilayah'], $row['kolektibilitas'], $row['classification_limited'], $row['source_kind'],
                ])->all(),
                $position['unknown'],
            ], JSON_THROW_ON_ERROR));

            if ($report->revision > 0 && hash_equals($report->source_hash, $hash)) {
                if ($report->needs_reconcile) {
                    $report->update(['needs_reconcile' => false]);
                }

                return ['status' => 'unchanged', 'revision' => $report->revision, 'known' => $rows->count(), 'unknown' => $position['unknown']];
            }

            PumkMonitoringPosition::query()->where('report_id', $report->id)->delete();
            foreach ($rows as $row) {
                PumkMonitoringPosition::query()->create([
                    'report_id' => $report->id,
                    'pinjaman_id' => $row['pinjaman_id'],
                    'mitra_id' => $row['mitra_id'],
                    'saldo_pokok' => $row['saldo_pokok'],
                    'saldo_bunga' => $row['saldo_bunga'],
                    'sektor' => $row['sektor'] === 'Belum Terverifikasi' ? null : $row['sektor'],
                    'wilayah' => $row['wilayah'] === 'Belum Terverifikasi' ? null : $row['wilayah'],
                    'kolektibilitas' => $row['kolektibilitas'] === 'Belum Dinilai' ? null : $row['kolektibilitas'],
                    'classification_limited' => $row['classification_limited'],
                    'source_kind' => $row['source_kind'],
                ]);
            }
            $report->update([
                'source_hash' => $hash,
                'source_updated_at' => $position['source_updated_at'],
                'revision' => $report->revision + 1,
                'known_loans' => $rows->count(),
                'unknown_loans' => $position['unknown'],
                'needs_reconcile' => false,
                'generated_at' => now(),
            ]);

            return ['status' => $report->revision === 1 ? 'created' : 'revised', 'revision' => $report->revision, 'known' => $rows->count(), 'unknown' => $position['unknown']];
        }, 3);
    }
}
