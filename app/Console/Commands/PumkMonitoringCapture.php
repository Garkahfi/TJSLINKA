<?php

namespace App\Console\Commands;

use App\Models\PumkMonitoringReport;
use App\Services\Monitoring\PumkMonitoringCaptureService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PumkMonitoringCapture extends Command
{
    protected $signature = 'pumk:monitoring-capture {--as-of= : Tanggal posisi YYYY-MM-DD; default akhir bulan sebelumnya} {--reconcile : Hitung ulang seluruh posisi yang pernah dicatat}';

    protected $description = 'Catat posisi saldo PUMK internal dengan tanggal posisi eksplisit';

    public function handle(PumkMonitoringCaptureService $capture): int
    {
        try {
            $asOf = $this->option('as-of')
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('as-of'), 'Asia/Jakarta')
                : CarbonImmutable::now('Asia/Jakarta')->startOfMonth()->subDay();
            if ($asOf === false || $asOf->format('Y-m-d') !== ($this->option('as-of') ?: $asOf->format('Y-m-d'))) {
                throw new InvalidArgumentException('Gunakan tanggal YYYY-MM-DD yang valid.');
            }
            $dates = $this->option('reconcile')
                ? PumkMonitoringReport::query()->orderBy('as_of_date')->pluck('as_of_date')
                    ->map(fn ($date): CarbonImmutable => CarbonImmutable::parse($date, 'Asia/Jakarta'))->push($asOf)->unique(fn (CarbonImmutable $date): string => $date->toDateString())
                : collect([$asOf]);
            foreach ($dates as $date) {
                $result = $capture->capture($date);
                $this->info("{$date->toDateString()}: {$result['status']}, revisi {$result['revision']}, {$result['known']} pinjaman diketahui, {$result['unknown']} belum dapat dihitung.");
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
