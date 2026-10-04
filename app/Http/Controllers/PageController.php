<?php

namespace App\Http\Controllers;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Models\Program;
use App\Models\ProgramDocument;
use App\Models\TerasPaket;
use App\Models\TerasProduk;
use App\Services\Monitoring\PumkDashboardService;
use App\Services\Monitoring\PumkInternalMonitoringService;
use App\Services\PublicPages\PublicProgramDetails;
use App\Services\PublicPages\PublicProgramDocuments;
use App\Services\PublicPages\PublicProgramOverview;
use App\Services\PublicPages\TjslDashboardPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageController extends Controller
{
    public function __construct(
        private readonly PumkDashboardService $pumkDashboard,
        private readonly PumkInternalMonitoringService $pumkInternalMonitoring,
    ) {}

    private function data(string $file): array
    {
        return json_decode(file_get_contents(resource_path("data/{$file}.json")), true) ?? [];
    }

    public function home(Request $request): View|RedirectResponse
    {
        if ($request->has('pumk_year')) {
            return redirect()->route('monitoring.bri', ['pumk_year' => $request->query('pumk_year')]);
        }

        return $this->monitoringTjsl();
    }

    public function monitoringTjsl(): View
    {
        return app(TjslDashboardPage::class)->monitoringTjsl();
    }

    public function monitoringBri(Request $request): View
    {
        $validated = $request->validate(['pumk_year' => ['nullable', 'integer', 'between:1900,2100']]);

        return view('pages.home', [
            'dashboardType' => 'bri',
            'faqs' => $this->data('faqs'),
            'pumkBriDashboard' => $this->pumkDashboard->bri(isset($validated['pumk_year']) ? (int) $validated['pumk_year'] : null),
        ]);
    }

    public function monitoringInka(Request $request): View
    {
        $validated = $request->validate(['year' => [
            'nullable', 'integer', 'min:'.PumkInternalMonitoringService::MIN_REPORT_YEAR,
            'max:'.Carbon::now('Asia/Jakarta')->year,
        ]]);

        return view('pages.home', [
            'dashboardType' => 'inka',
            'faqs' => $this->data('faqs'),
            'pumkLiveDashboard' => $this->pumkInternalMonitoring->report(isset($validated['year']) ? (int) $validated['year'] : null),
        ]);
    }

    public function teras(): View
    {
        return view('pages.teras-tjsl', [
            'products' => TerasProduk::query()
                ->where('is_active', true)
                ->latest()
                ->get(),
            'packages' => TerasPaket::query()
                ->where('is_active', true)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function overview(Request $request): View
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'in:2024,2025,2026'],
        ]);
        $keyword = trim($validated['keyword'] ?? '');
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $programs = app(PublicProgramOverview::class)->publicOverviewPrograms($keyword);

        return view('pages.program-overview', [
            'programs' => $programs,
            'news' => $this->data('news'),
            'searchKeyword' => $keyword,
            'monitoringSignature' => app(PublicProgramOverview::class)->monitoringRevision($keyword, $year),
        ]);
    }

    public function overviewMonitoring(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'in:2024,2025,2026'],
            'signature' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $keyword = trim($validated['keyword'] ?? '');
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $signature = app(PublicProgramOverview::class)->monitoringRevision($keyword, $year);

        if (hash_equals($signature, $validated['signature'] ?? '')) {
            return response()
                ->noContent()
                ->header('Cache-Control', 'private, no-store');
        }

        $programs = app(PublicProgramOverview::class)->publicOverviewPrograms($keyword);
        $html = view('components.program-monitoring-table', [
            'programs' => $programs,
            'emptyMessage' => 'Belum ada program yang telah disetujui Super Admin.',
            'showSearch' => true,
            'showCsrLegend' => true,
            'searchKeyword' => $keyword,
            'monitoringBaseUrl' => route('program.overview'),
            'monitoringSignature' => $signature,
        ])->render();

        return response()
            ->json([
                'signature' => $signature,
                'html' => $html,
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function rincian(Request $request): View
    {
        return app(PublicProgramDetails::class)->rincian($request);
    }

    public function detail(string $slug): View
    {
        return app(PublicProgramDetails::class)->detail($slug);
    }

    public function csrDetail(BantuanCsr $bantuanCsr): View
    {
        return app(PublicProgramDetails::class)->csrDetail($bantuanCsr);
    }

    public function viewCsrDocument(
        BantuanCsr $bantuanCsr,
        BantuanCsrDocument $document,
    ): BinaryFileResponse {
        return app(PublicProgramDocuments::class)->viewCsrDocument($bantuanCsr, $document);
    }

    public function downloadCsrDocument(
        BantuanCsr $bantuanCsr,
        BantuanCsrDocument $document,
    ): BinaryFileResponse {
        return app(PublicProgramDocuments::class)->downloadCsrDocument($bantuanCsr, $document);
    }

    public function viewProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): BinaryFileResponse {
        return app(PublicProgramDocuments::class)->viewProgramDocument($program, $document);
    }

    public function downloadProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): BinaryFileResponse {
        return app(PublicProgramDocuments::class)->downloadProgramDocument($program, $document);
    }
}
