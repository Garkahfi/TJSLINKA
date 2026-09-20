<?php

namespace App\Http\Controllers;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = $request->user()->id;
        $activePrograms = Program::where('created_by', $userId)
            ->where('is_archived', false)
            ->where('status', '!=', 'rejected_fase1');

        return view('admin.dashboard', [
            'pillars' => Pillar::orderBy('id')->get(),
            'pillarCounts' => (clone $activePrograms)
                ->selectRaw('pillar_id, count(*) total')
                ->groupBy('pillar_id')
                ->pluck('total', 'pillar_id'),
            'programs' => (clone $activePrograms)->with('pillar')->latest()->get(),
            'bantuan' => BantuanCsr::where('created_by', $userId)
                ->where('is_archived', false)
                ->latest()
                ->limit(4)
                ->get(),
        ]);
    }
}
