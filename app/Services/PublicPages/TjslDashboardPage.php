<?php

namespace App\Services\PublicPages;

use App\Models\BidangPrioritas;
use App\Models\Pillar;
use App\Models\TpbDashboard;
use App\Models\WilayahOperasional;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TjslDashboardPage
{
    public function monitoringTjsl(): View
    {
        $perPilar = Pillar::query()
            ->orderBy('id')
            ->get()
            ->map(
                fn (Pillar $pillar) => (object) [
                    'pillar_id' => $pillar->id,
                    'pillar' => $pillar,
                    'total_rencana' => (float) $pillar->dashboard_rencana_anggaran,
                    'total_realisasi' => (float) $pillar->dashboard_realisasi_anggaran,
                ],
            );

        $perWilayah = WilayahOperasional::query()
            ->orderByRaw('CASE WHEN nama = ? THEN 1 ELSE 0 END', ['Wilayah Lainnya'])
            ->orderByDesc('realisasi_anggaran')
            ->orderBy('nama')
            ->get();
        $bidangPrioritas = BidangPrioritas::query()
            ->orderBy('id')
            ->get();
        $tpbDashboard = TpbDashboard::query()
            ->get()
            ->sortBy(fn (TpbDashboard $tpb) => (int) preg_replace('/\D+/', '', $tpb->nomor_tpb))
            ->values();

        $totalRencana = (float) $perPilar->sum('total_rencana');
        $totalRealisasi = (float) $perPilar->sum('total_realisasi');
        $penyerapan = $totalRencana > 0
            ? min(100, round(($totalRealisasi / $totalRencana) * 100, 1))
            : 0;
        $dashboardUpdatedAt = collect([
            Pillar::query()->max('updated_at'),
            WilayahOperasional::query()->max('updated_at'),
            BidangPrioritas::query()->max('updated_at'),
            TpbDashboard::query()->max('updated_at'),
        ])
            ->filter()
            ->map(fn ($timestamp) => Carbon::parse($timestamp))
            ->sortDesc()
            ->first();

        return view('pages.home', [
            'dashboardType' => 'tjsl',
            'faqs' => json_decode(file_get_contents(resource_path('data/faqs.json')), true) ?? [],
            'perPilar' => $perPilar,
            'perWilayah' => $perWilayah,
            'bidangPrioritas' => $bidangPrioritas,
            'tpbDashboard' => $tpbDashboard,
            'totalRencana' => $totalRencana,
            'totalRealisasi' => $totalRealisasi,
            'penyerapan' => $penyerapan,
            'dashboardUpdatedAt' => $dashboardUpdatedAt,
        ]);
    }
}
