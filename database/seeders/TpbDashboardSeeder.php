<?php

namespace Database\Seeders;

use App\Models\TpbDashboard;
use Illuminate\Database\Seeder;

class TpbDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $tpb = [
            ['nomor_tpb' => 'TPB 1', 'rencana_anggaran' => 28, 'realisasi_anggaran' => 19],
            ['nomor_tpb' => 'TPB 2', 'rencana_anggaran' => 75, 'realisasi_anggaran' => 52],
            ['nomor_tpb' => 'TPB 3', 'rencana_anggaran' => 95, 'realisasi_anggaran' => 63],
            ['nomor_tpb' => 'TPB 4', 'rencana_anggaran' => 18, 'realisasi_anggaran' => 11],
            ['nomor_tpb' => 'TPB 5', 'rencana_anggaran' => 42, 'realisasi_anggaran' => 28],
            ['nomor_tpb' => 'TPB 6', 'rencana_anggaran' => 58, 'realisasi_anggaran' => 39],
            ['nomor_tpb' => 'TPB 7', 'rencana_anggaran' => 35, 'realisasi_anggaran' => 22],
        ];

        foreach ($tpb as $item) {
            TpbDashboard::query()->updateOrCreate(
                ['nomor_tpb' => $item['nomor_tpb']],
                [
                    'rencana_anggaran' => $item['rencana_anggaran'],
                    'realisasi_anggaran' => $item['realisasi_anggaran'],
                ],
            );
        }
    }
}
