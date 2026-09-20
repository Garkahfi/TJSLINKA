<?php

namespace App\Http\Controllers;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeProgramQuery = Program::where('is_archived', false)
            ->whereIn('status', Program::PUBLIC_STATUSES);

        return view('superadmin.dashboard', [
            'stats' => [
                'program_pending' => (clone $activeProgramQuery)
                    ->whereIn('status', ['pending_fase1', 'pending_fase2'])
                    ->count(),
                'program_approved' => (clone $activeProgramQuery)
                    ->where('status', 'completed')
                    ->count(),
                'assistance_pending' => BantuanCsr::whereIn('status', [
                    'pending_fase1',
                    'pending_fase2',
                ])
                    ->where('is_archived', false)
                    ->count(),
                'assistance_approved' => BantuanCsr::where('status', 'completed')
                    ->where('is_archived', false)
                    ->count(),
            ],
            'pillars' => Pillar::orderBy('id')->get(),
            'pillarCounts' => (clone $activeProgramQuery)
                ->selectRaw('pillar_id, count(*) total')
                ->groupBy('pillar_id')
                ->pluck('total', 'pillar_id'),
            'bantuan' => BantuanCsr::with('creator')
                ->where('is_archived', false)
                ->whereIn('status', [
                    'pending_fase1',
                    'approved_fase1',
                    'pending_fase2',
                    'completed',
                ])
                ->latest()
                ->limit(4)
                ->get(),
        ]);
    }
}
