<?php

namespace Database\Seeders;

use App\Models\BidangPrioritas;
use Illuminate\Database\Seeder;

class BidangPrioritasSeeder extends Seeder
{
    public function run(): void
    {
        $bidang = [
            ['nama_bidang' => 'Bidang Pendidikan', 'penyerapan_persen' => 85.0],
            ['nama_bidang' => 'Bidang Pengembangan UMK', 'penyerapan_persen' => 52.0],
            ['nama_bidang' => 'Bidang Lingkungan', 'penyerapan_persen' => 31.0],
        ];

        foreach ($bidang as $item) {
            BidangPrioritas::query()->updateOrCreate(
                ['nama_bidang' => $item['nama_bidang']],
                ['penyerapan_persen' => $item['penyerapan_persen']],
            );
        }
    }
}
