<?php

namespace Database\Seeders;

use App\Models\TerasPaket;
use App\Models\TerasProduk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class TerasTjslSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('username', 'TJSLINKA')
            ->first();

        if (! $admin) {
            throw new RuntimeException('Akun Admin TJSLINKA belum tersedia. Jalankan UserSeeder sebelum TerasTjslSeeder.');
        }

        $this->seedProducts($admin);
        $this->seedPackages($admin);
    }

    private function seedProducts(User $admin): void
    {
        $path = resource_path('data/products.json');
        $products = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($products as $product) {
            TerasProduk::query()->updateOrCreate(
                [
                    'nama_produk' => $product['name'],
                    'nama_umkm' => $product['business'],
                ],
                [
                    'foto_path' => $product['image'] ?: null,
                    'deskripsi' => null,
                    'is_active' => true,
                    'created_by' => $admin->id,
                ],
            );
        }
    }

    private function seedPackages(User $admin): void
    {
        foreach ($this->packages() as $package) {
            TerasPaket::query()->updateOrCreate(
                ['nama_paket' => $package['nama_paket']],
                [...$package, 'created_by' => $admin->id],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        return [
            [
                'nama_paket' => 'Paket A',
                'foto_path' => 'images/teras/paket-a.png',
                'harga' => 800000,
                'tipe_harga' => 'tetap',
                'isi_paket' => [
                    'Batik Kereta',
                    'Bluder',
                    'Sambal Pecel',
                    'Abon Ayam',
                    'Brem',
                    'Pie Telo',
                    'Kopi Robusta Megeti',
                    'Carrissa Snack',
                    'Kerupuk Telur Asin',
                    'Keripik Paru Daun Singkong',
                    'Telang Sereh Celup',
                ],
                'catatan_khusus' => null,
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama_paket' => 'Paket B',
                'foto_path' => 'images/teras/paket-b.png',
                'harga' => 600000,
                'tipe_harga' => 'tetap',
                'isi_paket' => [
                    'Batik Kereta',
                    'Miniatur Kereta',
                    'Bluder',
                    'Sambal Pecel',
                    'Abon Ayam',
                    'Brem',
                    'Pie Telo',
                    'Kopi Robusta Megeti',
                    'Carrissa Snack',
                    'Kerupuk Telur Asin',
                    'Keripik Paru Daun Singkong',
                    'Telang Sereh Celup',
                ],
                'catatan_khusus' => null,
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama_paket' => 'Paket C',
                'foto_path' => 'images/teras/paket-c.png',
                'harga' => 450000,
                'tipe_harga' => 'tetap',
                'isi_paket' => [
                    'Bluder',
                    'Sambal Pecel',
                    'Abon Ayam',
                    'Brem',
                    'Pie Telo',
                    'Kopi Robusta Megeti',
                    'Carrissa Snack',
                    'Kerupuk Telur Asin',
                    'Keripik Jamur',
                    'Telang Sereh Celup',
                    'Abon Ikan Tuna',
                ],
                'catatan_khusus' => null,
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'nama_paket' => 'Paket D',
                'foto_path' => 'images/teras/paket-d.png',
                'harga' => 1000000,
                'tipe_harga' => 'maksimal',
                'isi_paket' => [
                    'Batik Kereta',
                    'Kreasi Ecoprint dan Kriya',
                    'Miniatur Kereta',
                    'Bluder',
                    'Sambal Pecel',
                    'Abon Ayam',
                    'Abon Ikan Tuna',
                    'Mie Ammatri',
                    'Telang Sereh Celup',
                    'Kopi Arabica Megeti',
                    'Kopi Mix Jagung',
                    'Kopi Java Lawu',
                    'Carrissa Snack',
                    'Brem Original',
                    'Brem 5 Rasa',
                    'Brem Kelor',
                    'Pie Telo Mix',
                    'Pie Brownies Telo',
                    'Chocotelo',
                    'Kopi Robusta Megeti',
                    'Produk Minuman Biofarmaka',
                    'Ginger Snack Sticks',
                    'Telur Asin Aneka Rasa',
                    'Kerupuk Telur Asin',
                    'Keripik Paru Daun Singkong',
                    'Keripik Jamur',
                ],
                'catatan_khusus' => "Paket A-C: Paket siap pilih dari UMKM.\nPaket D: Custom paket sesuai kebutuhan dengan maksimal budget Rp1.000.000.",
                'urutan' => 4,
                'is_active' => true,
            ],
        ];
    }
}
