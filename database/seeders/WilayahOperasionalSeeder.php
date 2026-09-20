<?php

namespace Database\Seeders;

use App\Models\WilayahOperasional;
use Illuminate\Database\Seeder;

class WilayahOperasionalSeeder extends Seeder
{
    public function run(): void
    {
        $wilayah = [
            ['nama' => 'Kota Madiun', 'latitude' => -7.6298, 'longitude' => 111.5239, 'realisasi_anggaran' => 1092580101],
            ['nama' => 'Kab. Madiun', 'latitude' => -7.5148, 'longitude' => 111.5361, 'realisasi_anggaran' => 751491882],
            ['nama' => 'Kab. Banyuwangi', 'latitude' => -8.2192, 'longitude' => 114.3691, 'realisasi_anggaran' => 616280152],
            ['nama' => 'Kab. Ngawi', 'latitude' => -7.4034, 'longitude' => 111.4464, 'realisasi_anggaran' => 52454600],
            ['nama' => 'Kab. Magetan', 'latitude' => -7.6478, 'longitude' => 111.3325, 'realisasi_anggaran' => 11006500],
            ['nama' => 'Kab. Ponorogo', 'latitude' => -7.8681, 'longitude' => 111.4634, 'realisasi_anggaran' => 5002500],
            ['nama' => 'Kab. Pacitan', 'latitude' => -8.1937, 'longitude' => 111.1044, 'realisasi_anggaran' => 0],
            ['nama' => 'Wilayah Lainnya', 'latitude' => -7.5000, 'longitude' => 111.7500, 'realisasi_anggaran' => 94845716],
        ];

        foreach ($wilayah as $item) {
            WilayahOperasional::query()->updateOrCreate(
                ['nama' => $item['nama']],
                [
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                    'realisasi_anggaran' => $item['realisasi_anggaran'],
                ],
            );
        }
    }
}
