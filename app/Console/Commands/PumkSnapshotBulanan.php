<?php

namespace App\Console\Commands;

use App\Services\Monitoring\PumkSnapshotService;
use Illuminate\Console\Command;

class PumkSnapshotBulanan extends Command
{
    protected $signature = 'pumk:snapshot-bulanan';

    protected $description = 'Simpan snapshot saldo PUMK per sektor dan kolektibilitas untuk bulan berjalan';

    public function handle(PumkSnapshotService $snapshots): int
    {
        $result = $snapshots->capture(now());

        $this->info(
            "Snapshot PUMK selesai: {$result['dibuat']} dibuat, {$result['sudah_ada']} sudah tersedia.",
        );

        return self::SUCCESS;
    }
}
