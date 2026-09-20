<?php

namespace Database\Seeders;

use App\Models\Pillar;
use Illuminate\Database\Seeder;

class PillarSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Sosial', '#2563eb', 'sosial'], ['Ekonomi', '#f59e0b', 'ekonomi'], ['Lingkungan', '#16a34a', 'lingkungan'], ['Hukum & Tata Kelola', '#dc2626', 'hukum-tata-kelola']] as [$name,$color,$slug]) {
            Pillar::updateOrCreate(['slug' => $slug], ['name' => $name, 'color_hex' => $color]);
        }
    }
}
