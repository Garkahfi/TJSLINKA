<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PillarSeeder::class,
            WilayahOperasionalSeeder::class,
            BidangPrioritasSeeder::class,
            TpbDashboardSeeder::class,
            UserSeeder::class,
            PumkAdminSeeder::class,
            PumkReferenceSeeder::class,
            TerasTjslSeeder::class,
        ]);
    }
}
