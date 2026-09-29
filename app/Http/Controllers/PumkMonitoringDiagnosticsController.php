<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\PumkInternalMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PumkMonitoringDiagnosticsController extends Controller
{
    public function __invoke(Request $request, PumkInternalMonitoringService $monitoring): View
    {
        $validated = $request->validate(['year' => [
            'nullable', 'integer', 'min:'.PumkInternalMonitoringService::MIN_REPORT_YEAR,
            'max:'.CarbonImmutable::now('Asia/Jakarta')->year,
        ]]);
        $result = $monitoring->diagnostics(isset($validated['year']) ? (int) $validated['year'] : null);
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $items = collect($result['items']);
        $rows = new LengthAwarePaginator($items->forPage($page, 20)->values(), $items->count(), 20, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('pumk-admin.monitoring.diagnostics', [
            'report' => $result['report'], 'rows' => $rows,
            'isSuperadmin' => $request->routeIs('superadmin.*'),
        ]);
    }
}
