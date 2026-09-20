<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $totals = DB::table('programs')
            ->where('status', 'completed')
            ->selectRaw('pillar_id, SUM(rencana_anggaran) AS rencana, SUM(realisasi_anggaran) AS realisasi')
            ->groupBy('pillar_id')
            ->get();

        foreach ($totals as $total) {
            DB::table('pillars')
                ->where('id', $total->pillar_id)
                ->update([
                    'dashboard_rencana_anggaran' => $total->rencana ?? 0,
                    'dashboard_realisasi_anggaran' => $total->realisasi ?? 0,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Data cache tidak dikosongkan saat rollback agar dashboard lama tetap aman.
    }
};
