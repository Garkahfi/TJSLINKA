<?php

namespace App\Http\Controllers;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use Illuminate\View\View;

class PumkAdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $collectibility = PumkPinjaman::query()
            ->where('is_active', true)
            ->where('status', PumkPinjaman::STATUS_AKTIF)
            ->whereNotNull('kolektibilitas')
            ->selectRaw('kolektibilitas, COUNT(*) as total')
            ->groupBy('kolektibilitas')
            ->pluck('total', 'kolektibilitas');

        return view('pumk-admin.dashboard', [
            'totalMitra' => PumkMitra::query()->whereHas('pinjamanAktif')->count(),
            'totalPinjaman' => PumkPinjaman::query()->where('is_active', true)->where('status', PumkPinjaman::STATUS_AKTIF)->count(),
            'totalMitraLunas' => PumkMitra::query()
                ->whereDoesntHave('pinjamanAktif')
                ->whereHas('pinjaman', fn ($query) => $query->where('status', PumkPinjaman::STATUS_LUNAS))
                ->count(),
            'totalPinjamanLunas' => PumkPinjaman::query()->where('status', PumkPinjaman::STATUS_LUNAS)->count(),
            'dataBelumLengkap' => PumkMitra::query()
                ->whereHas('pinjamanAktif')
                ->where(function ($query): void {
                    $query->whereNull('wilayah_id')
                        ->orWhereNull('sektor_usaha_id')
                        ->orWhereNull('nama_pemilik');
                })
                ->count(),
            'collectibility' => $collectibility,
        ]);
    }
}
