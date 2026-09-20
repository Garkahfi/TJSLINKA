<?php

namespace Database\Seeders;

use App\Models\PumkSektorUsaha;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PumkReferenceSeeder extends Seeder
{
    /**
     * Sektor yang benar-benar ditemukan pada workbook DB PUMK REVISI.xlsx.
     */
    private const SEKTOR_USAHA = [
        'Industri - Makanan Minuman',
        'Industri - Meubel',
        'Industri - Bengkel / Pertukangan',
        'Industri - Pengolahan',
        'Peternakan',
        'Industri - Kreatif',
        'Perdagangan',
        'Industri - Konveksi',
        'Jasa',
        'Lainnya',
        'Pertanian',
    ];

    public function run(): void
    {
        $activeSlugs = [];

        foreach (self::SEKTOR_USAHA as $nama) {
            $slug = Str::slug(str_replace('/', ' ', $nama));
            $activeSlugs[] = $slug;

            PumkSektorUsaha::updateOrCreate(
                ['slug' => $slug],
                ['nama' => $nama, 'is_active' => true],
            );
        }

        // Seeder versi awal pernah membuat enam nama sektor tanpa prefix
        // "Industri -". Record tersebut tidak dipakai workbook. Pertahankan
        // untuk jejak referensi, tetapi sembunyikan dari pilihan form agar
        // Admin PUMK hanya melihat 11 kategori sumber yang benar.
        PumkSektorUsaha::query()
            ->whereNotIn('slug', $activeSlugs)
            ->whereDoesntHave('mitra')
            ->update(['is_active' => false]);
    }
}
