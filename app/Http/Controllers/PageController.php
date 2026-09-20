<?php

namespace App\Http\Controllers;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Models\BidangPrioritas;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\ProgramDocument;
use App\Models\TerasPaket;
use App\Models\TerasProduk;
use App\Models\TpbDashboard;
use App\Models\WilayahOperasional;
use App\Services\Monitoring\PumkDashboardService;
use App\Services\ProgramMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageController extends Controller
{
    public function __construct(
        private readonly ProgramMonitoringService $programMonitoring,
        private readonly PumkDashboardService $pumkDashboard,
    ) {}

    private function data(string $file): array
    {
        return json_decode(file_get_contents(resource_path("data/{$file}.json")), true) ?? [];
    }

    public function home(Request $request): View
    {
        $requestedPumkYear = $request->integer('pumk_year') ?: null;
        $pumkBriDashboard = $this->pumkDashboard->bri($requestedPumkYear);
        $pumkLiveDashboard = $this->pumkDashboard->live();
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
            ->first() ?? now();

        return view('pages.home', [
            'faqs' => $this->data('faqs'),
            'perPilar' => $perPilar,
            'perWilayah' => $perWilayah,
            'bidangPrioritas' => $bidangPrioritas,
            'tpbDashboard' => $tpbDashboard,
            'totalRencana' => $totalRencana,
            'totalRealisasi' => $totalRealisasi,
            'penyerapan' => $penyerapan,
            'dashboardUpdatedAt' => $dashboardUpdatedAt,
            'pumkBriDashboard' => $pumkBriDashboard,
            'pumkLiveDashboard' => $pumkLiveDashboard,
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
        $programs = $this->publicOverviewPrograms($keyword);

        return view('pages.program-overview', [
            'programs' => $programs,
            'news' => $this->data('news'),
            'searchKeyword' => $keyword,
            'monitoringSignature' => $this->monitoringRevision($keyword, $year),
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
        $signature = $this->monitoringRevision($keyword, $year);

        if (hash_equals($signature, $validated['signature'] ?? '')) {
            return response()
                ->noContent()
                ->header('Cache-Control', 'private, no-store');
        }

        $programs = $this->publicOverviewPrograms($keyword);
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
        $pillars = Pillar::query()->orderBy('id')->get();
        $selectedPillar = $request->filled('pilar')
            ? $pillars->firstWhere('slug', $request->query('pilar'))
            : null;

        abort_if($request->filled('pilar') && ! $selectedPillar, 404);

        $internalQuery = Program::query()
            ->selectRaw("id, 'internal' as jenis, pillar_id, updated_at")
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->where('is_archived', false)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id));

        $csrQuery = BantuanCsr::query()
            ->selectRaw("id, 'csr' as jenis, pillar_id, updated_at")
            ->whereIn('status', BantuanCsr::OVERVIEW_STATUSES)
            ->where('is_archived', false)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id));

        $programs = DB::query()
            ->fromSub($internalQuery->unionAll($csrQuery), 'rincian_programs')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();

        $references = $programs->getCollection();
        $internalPrograms = Program::with('pillar', 'documents', 'photos', 'tujuan')
            ->whereIn('id', $references->where('jenis', 'internal')->pluck('id'))
            ->get()
            ->keyBy('id');
        $csrPrograms = BantuanCsr::with('pillar', 'photos', 'details.photos')
            ->whereIn('id', $references->where('jenis', 'csr')->pluck('id'))
            ->get()
            ->keyBy('id');

        $programs->setCollection($references->map(function (object $reference) use ($internalPrograms, $csrPrograms): array {
            if ($reference->jenis === 'csr') {
                return $this->csrCardArray($csrPrograms->get($reference->id));
            }

            return $this->programArray($internalPrograms->get($reference->id));
        }));

        return view('pages.program-rincian', compact('programs', 'pillars', 'selectedPillar'));
    }

    public function detail(string $slug): View
    {
        /** @var Program $model */
        $model = Program::with('pillar', 'documents', 'photos', 'tujuan')
            ->where('slug', $slug)
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->where('is_archived', false)
            ->firstOrFail();
        $program = $this->programArray($model);

        return view('pages.program-detail', compact('program'));
    }

    public function csrDetail(BantuanCsr $bantuanCsr): View
    {
        abort_unless(
            in_array($bantuanCsr->status, BantuanCsr::OVERVIEW_STATUSES, true)
                && ! $bantuanCsr->is_archived,
            404,
        );

        $bantuanCsr->load([
            'pillar',
            'targets',
            'documents',
            'photos',
            'details.photos',
        ]);

        return view('pages.csr-detail', [
            'program' => $this->csrDetailArray($bantuanCsr),
        ]);
    }

    public function viewCsrDocument(
        BantuanCsr $bantuanCsr,
        BantuanCsrDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicCsrDocument($bantuanCsr, $document);

        return response()->file(
            Storage::disk('local')->path($document->file_path),
            ['Content-Disposition' => 'inline; filename="'.$this->csrDocumentFilename($document).'"'],
        );
    }

    public function downloadCsrDocument(
        BantuanCsr $bantuanCsr,
        BantuanCsrDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicCsrDocument($bantuanCsr, $document);

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $this->csrDocumentFilename($document),
        );
    }

    public function viewProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicProgramDocument($program, $document);

        return response()->file(
            Storage::disk('local')->path($document->file_path),
            ['Content-Disposition' => 'inline; filename="'.$this->programDocumentFilename($document).'"'],
        );
    }

    public function downloadProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicProgramDocument($program, $document);

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $this->programDocumentFilename($document),
        );
    }

    /**
     * Overview hanya menampilkan program yang setidaknya sudah memperoleh
     * persetujuan awal Super Admin. Draft, pengajuan yang masih menunggu
     * pemeriksaan awal, dan pengajuan yang ditolak tetap bersifat internal.
     */
    private function publicOverviewPrograms(string $keyword = ''): array
    {
        $internalPrograms = Program::with([
            'pillar',
            'documents',
            'creator',
            'fase1Reviewer',
            'fase2Reviewer',
        ])
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program', 'like', "%{$keyword}%"),
            )
            ->latest()
            ->get()
            ->map(function (Program $program): array {
                $detailUrl = ! $program->is_archived
                    && in_array($program->status, Program::PUBLIC_STATUSES, true)
                        ? route('program.detail', $program->slug)
                        : null;

                return $this->programMonitoring->internal($program, $detailUrl);
            });

        $csrPrograms = BantuanCsr::with([
            'pillar',
            'documents',
            'creator',
            'fase1Reviewer',
            'fase2Reviewer',
        ])
            ->whereIn('status', BantuanCsr::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program_bantuan', 'like', "%{$keyword}%"),
            )
            ->latest()
            ->get()
            ->map(fn (BantuanCsr $program): array => $this->programMonitoring->csr($program));

        return $internalPrograms
            ->concat($csrPrograms)
            ->sortByDesc('sort_timestamp')
            ->values()
            ->all();
    }

    /**
     * Tiga query ringkas ini menghindari eager-loading seluruh relasi pada
     * setiap poll. Count dokumen disertakan supaya perubahan checklist A-F
     * langsung mengubah revision meskipun timestamp berada pada detik sama.
     */
    private function monitoringRevision(string $keyword, ?int $year): string
    {
        $internal = Program::query()
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program', 'like', "%{$keyword}%"),
            )
            ->select([
                'id',
                'slug',
                'pillar_id',
                'jenis_kerjasama',
                'nama_program',
                'status',
                'is_archived',
                'fase1_reviewed_at',
                'fase2_reviewed_at',
                'updated_at',
            ])
            ->withCount([
                'documents as document_a_count' => fn ($query) => $query
                    ->whereIn('document_type', [
                        'A',
                        'proposal_pengajuan_program',
                        'surat_penawaran_balasan',
                    ]),
                'documents as document_b_count' => fn ($query) => $query
                    ->whereIn('document_type', [
                        'B',
                        'kelengkapan_survei',
                        'bukti_penjajakan',
                    ]),
                'documents as document_c_count' => fn ($query) => $query
                    ->whereIn('document_type', ['C', 'kajian_kelayakan']),
                'documents as document_d_count' => fn ($query) => $query
                    ->whereIn('document_type', ['D', 'kajian_mitigasi_risiko']),
                'documents as document_e_count' => fn ($query) => $query
                    ->whereIn('document_type', ['E', 'perjanjian_kerja_sama']),
                'documents as document_f_count' => fn ($query) => $query
                    ->whereIn('document_type', ['F', 'bast']),
            ])
            ->orderBy('id')
            ->get()
            ->toArray();

        $csr = BantuanCsr::query()
            ->whereIn('status', BantuanCsr::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program_bantuan', 'like', "%{$keyword}%"),
            )
            ->select([
                'id',
                'pillar_id',
                'nama_program_bantuan',
                'status',
                'fase1_reviewed_at',
                'fase2_reviewed_at',
                'updated_at',
            ])
            ->withCount([
                'documents as document_a_count' => fn ($query) => $query
                    ->whereIn('document_type', ['A', 'proposal_pengajuan_program']),
                'documents as document_b_count' => fn ($query) => $query
                    ->whereIn('document_type', ['B', 'kelengkapan_survei']),
                'documents as document_f_count' => fn ($query) => $query
                    ->whereIn('document_type', ['F', 'bast']),
            ])
            ->orderBy('id')
            ->get()
            ->toArray();

        $pillars = Pillar::query()
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'color_hex', 'updated_at'])
            ->toArray();

        return hash('sha256', json_encode([
            'keyword' => $keyword,
            'year' => $year,
            'internal' => $internal,
            'csr' => $csr,
            'pillars' => $pillars,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function programArray(Program $program): array
    {
        $photos = $program->photos->map(fn ($photo) => Storage::url($photo->file_path))->all();
        $legacyGoal = trim((string) $program->tujuan_program);
        $goals = $legacyGoal !== ''
            ? [[
                'description' => $legacyGoal,
                'image' => null,
            ]]
            : $program->tujuan->map(fn ($tujuan) => [
                'description' => $tujuan->deskripsi,
                'image' => $tujuan->foto_path ? Storage::url($tujuan->foto_path) : null,
            ])->all();

        return [
            'id' => $program->id,
            'jenis' => 'internal',
            'jenis_kerjasama' => $program->jenis_kerjasama ?: 'pks',
            'slug' => $program->slug,
            'pillar' => $program->pillar->slug,
            'title' => $program->nama_program,
            'status' => $program->status,
            'detail_url' => route('program.detail', $program->slug),
            'cover_image' => $photos[0] ?? 'images/programs/sample-1.png',
            'description' => $program->deskripsi_program,
            'sasaran' => $program->sasaran_program,
            'lokasi' => $program->lokasi_program,
            'mitra' => $program->mitra_program,
            'budget_planned' => (float) $program->rencana_anggaran,
            'budget_realized' => (float) $program->realisasi_anggaran,
            'gallery' => $photos,
            'goals' => $goals,
            'document_types' => $program->documents->pluck('document_type')->unique()->values()->all(),
            'documents' => $program->documents->map(fn ($document) => [
                'name' => $document->nama_dokumen,
                'checked' => true,
                'view_url' => route('program.documents.view', [$program, $document]),
                'download_url' => route('program.documents.download', [$program, $document]),
            ])->all(),
            'sort_timestamp' => $program->updated_at?->timestamp ?? 0,
        ];
    }

    private function csrCardArray(BantuanCsr $program): array
    {
        $coverImage = $program->photos->first()?->file_path
            ?? $program->details->flatMap->photos->first()?->file_path;

        return [
            'id' => $program->id,
            'jenis' => 'csr',
            'slug' => null,
            'pillar' => $program->pillar?->slug ?? 'belum-ditentukan',
            'title' => $program->nama_program_bantuan,
            'status' => $program->status,
            'detail_url' => route('program.csr.detail', $program),
            'cover_image' => $coverImage
                ? Storage::url($coverImage)
                : null,
            'sort_timestamp' => $program->updated_at?->timestamp ?? 0,
        ];
    }

    private function csrDetailArray(BantuanCsr $program): array
    {
        $programPhotos = $program->photos
            ->map(fn ($photo): array => [
                'url' => Storage::url($photo->file_path),
                'caption' => $photo->caption,
            ]);
        $detailPhotos = $program->details
            ->flatMap(fn ($detail) => $detail->photos->map(fn ($photo): array => [
                'url' => Storage::url($photo->file_path),
                'caption' => $photo->caption,
            ]));
        $gallery = $programPhotos
            ->concat($detailPhotos)
            ->unique('url')
            ->values();
        $targetImages = $detailPhotos
            ->concat($programPhotos)
            ->unique('url')
            ->values();

        return [
            'id' => $program->id,
            'title' => $program->nama_program_bantuan,
            'pillar' => $program->pillar?->slug,
            'description' => $program->deskripsi_bantuan,
            'status' => $program->status,
            'budget_planned' => (float) $program->rencana_anggaran,
            'budget_realized' => (float) $program->realisasi_anggaran,
            'cover_image' => $gallery->first()['url'] ?? null,
            'gallery' => $gallery->all(),
            'targets' => $program->targets->values()->map(fn ($target, int $index): array => [
                'description' => $target->target_text,
                'image' => $targetImages->get($index)['url'] ?? null,
            ])->all(),
            'details' => $program->details->values()->map(fn ($detail): array => [
                'activity' => $detail->rincian_kegiatan,
                'recipient' => $detail->penerima_bantuan,
                'assistance_type' => $detail->jenis_bantuan,
                'quantity' => $detail->quality,
                'nominal' => (float) $detail->nominal_bantuan,
            ])->all(),
            'documents' => $program->documents->values()->map(fn (BantuanCsrDocument $document): array => [
                'name' => $document->nama_dokumen,
                'type' => $this->csrDocumentLabel($document->document_type),
                'checked' => true,
                'view_url' => route('program.csr.documents.view', [$program, $document]),
                'download_url' => route('program.csr.documents.download', [$program, $document]),
            ])->all(),
        ];
    }

    private function csrDocumentLabel(string $type): string
    {
        return match ($type) {
            'A', 'proposal_pengajuan_program', 'proposal_permintaan' => 'Proposal Pengajuan Program',
            'B', 'kelengkapan_survei' => 'Kelengkapan Survei',
            'F', 'bast' => 'Berita Acara Serah Terima (BAST)',
            BantuanCsr::PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE => 'Dokumen Tambahan BAST',
            default => 'Dokumen Bantuan TJSL',
        };
    }

    private function authorizePublicCsrDocument(
        BantuanCsr $program,
        BantuanCsrDocument $document,
    ): void {
        abort_unless(
            $document->bantuan_csr_id === $program->id
                && in_array($program->status, BantuanCsr::OVERVIEW_STATUSES, true)
                && ! $program->is_archived
                && Storage::disk('local')->exists($document->file_path),
            404,
        );
    }

    private function authorizePublicProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): void {
        abort_unless(
            $document->program_id === $program->id
                && in_array($program->status, Program::OVERVIEW_STATUSES, true)
                && ! $program->is_archived
                && Storage::disk('local')->exists($document->file_path),
            404,
        );
    }

    private function programDocumentFilename(ProgramDocument $document): string
    {
        $name = trim(str_replace(['/', '\\', '"'], '-', $document->nama_dokumen));
        $name = $name !== '' ? $name : basename($document->file_path);
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$extension;
        }

        return $name;
    }

    private function csrDocumentFilename(BantuanCsrDocument $document): string
    {
        $name = trim(str_replace(['/', '\\', '"'], '-', $document->nama_dokumen));
        $name = $name !== '' ? $name : basename($document->file_path);
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$extension;
        }

        return $name;
    }
}
