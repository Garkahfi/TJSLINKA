<?php

namespace Tests\Feature;

use App\Models\BidangPrioritas;
use App\Models\WilayahOperasional;
use Database\Seeders\BidangPrioritasSeeder;
use Database\Seeders\WilayahOperasionalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_bidang_prioritas_schema_and_seed_data_are_available(): void
    {
        $this->seed(BidangPrioritasSeeder::class);

        $this->assertTrue(Schema::hasColumns('bidang_prioritas', [
            'nama_bidang',
            'rencana_anggaran',
            'realisasi_anggaran',
            'penyerapan_persen',
        ]));

        $this->assertDatabaseCount('bidang_prioritas', 3);
        $this->assertDatabaseHas('bidang_prioritas', [
            'nama_bidang' => 'Bidang Pendidikan',
            'penyerapan_persen' => 85,
        ]);
        $this->assertDatabaseHas('bidang_prioritas', [
            'nama_bidang' => 'Bidang Pengembangan UMK',
            'penyerapan_persen' => 52,
        ]);
        $this->assertDatabaseHas('bidang_prioritas', [
            'nama_bidang' => 'Bidang Lingkungan',
            'penyerapan_persen' => 31,
        ]);
    }

    public function test_reference_seeders_are_idempotent_and_include_pacitan(): void
    {
        $this->seed([
            BidangPrioritasSeeder::class,
            WilayahOperasionalSeeder::class,
        ]);
        $this->seed([
            BidangPrioritasSeeder::class,
            WilayahOperasionalSeeder::class,
        ]);

        $this->assertSame(3, BidangPrioritas::query()->count());
        $this->assertSame(8, WilayahOperasional::query()->count());
        $this->assertSame(1, WilayahOperasional::query()->where('nama', 'Kab. Pacitan')->count());

        $pacitan = WilayahOperasional::query()->where('nama', 'Kab. Pacitan')->firstOrFail();

        $this->assertSame(-8.1937, $pacitan->latitude);
        $this->assertSame(111.1044, $pacitan->longitude);
        $this->assertSame(0.0, (float) $pacitan->realisasi_anggaran);
    }

    public function test_regions_can_be_ordered_from_highest_to_lowest_realisasi(): void
    {
        $this->seed(WilayahOperasionalSeeder::class);

        $regions = WilayahOperasional::query()
            ->orderByDesc('realisasi_anggaran')
            ->orderBy('nama')
            ->get();

        $this->assertSame('Kota Madiun', $regions->first()->nama);
        $this->assertSame('Kab. Pacitan', $regions->last()->nama);
        $this->assertSame(0.0, (float) $regions->last()->realisasi_anggaran);
    }
}
