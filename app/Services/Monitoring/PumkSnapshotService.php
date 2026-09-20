<?php

namespace App\Services\Monitoring;

use App\Models\PumkSnapshotBulanan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PumkSnapshotService
{
    /**
     * Snapshot hanya dibuat sekali untuk setiap bulan/kategori. Menjalankan
     * command kembali pada bulan yang sama tidak mengubah titik historis.
     *
     * @return array{dibuat:int, sudah_ada:int}
     */
    public function capture(CarbonInterface $at): array
    {
        $rows = $this->sectorRows()->concat($this->collectibilityRows());
        $created = 0;
        $existing = 0;

        DB::transaction(function () use ($rows, $at, &$created, &$existing): void {
            foreach ($rows as $row) {
                $snapshot = PumkSnapshotBulanan::query()->firstOrCreate(
                    [
                        'bulan' => $at->month,
                        'tahun' => $at->year,
                        'kategori' => $row->kategori,
                        'tipe' => $row->tipe,
                    ],
                    ['nilai' => $row->nilai],
                );

                $snapshot->wasRecentlyCreated ? $created++ : $existing++;
            }
        });

        return ['dibuat' => $created, 'sudah_ada' => $existing];
    }

    private function sectorRows()
    {
        return DB::table('pumk_pinjaman as p')
            ->join('pumk_mitra as m', 'p.mitra_id', '=', 'm.id')
            ->leftJoin('pumk_sektor_usaha as s', 'm.sektor_usaha_id', '=', 's.id')
            ->where('p.is_active', true)
            ->where('m.is_active', true)
            ->selectRaw("COALESCE(s.nama, m.sektor_sumber, 'Belum Ditentukan') as kategori")
            ->selectRaw("'sektor' as tipe")
            ->selectRaw('SUM(COALESCE(p.total_sisa, p.total_pinjaman, 0)) as nilai')
            ->groupByRaw("COALESCE(s.nama, m.sektor_sumber, 'Belum Ditentukan')")
            ->get();
    }

    private function collectibilityRows()
    {
        return DB::table('pumk_pinjaman as p')
            ->join('pumk_mitra as m', 'p.mitra_id', '=', 'm.id')
            ->where('p.is_active', true)
            ->where('m.is_active', true)
            ->selectRaw("COALESCE(NULLIF(p.kolektibilitas, ''), 'Belum Dinilai') as kategori")
            ->selectRaw("'kolektibilitas' as tipe")
            ->selectRaw('SUM(COALESCE(p.total_sisa, p.total_pinjaman, 0)) as nilai')
            ->groupByRaw("COALESCE(NULLIF(p.kolektibilitas, ''), 'Belum Dinilai')")
            ->get();
    }
}
