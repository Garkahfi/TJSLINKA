<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PumkMitraPages
{
    public function index(Request $request, PumkCollectibilitySummaryService $collectibilitySummary): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'wilayah' => ['nullable', 'integer', 'exists:pumk_wilayah,id'],
            'sektor' => ['nullable', 'integer', 'exists:pumk_sektor_usaha,id'],
            'kolektibilitas' => ['nullable', Rule::in($this->collectibilityValues())],
            'status' => ['nullable', Rule::in(['aktif', 'lunas', 'semua'])],
        ]);
        $statusFilter = $filters['status'] ?? 'aktif';
        $selectedCollectibility = $filters['kolektibilitas'] ?? null;
        $selectedRecap = $selectedCollectibility !== null
            ? $collectibilitySummary->matchingLoanIdsWithSummary($filters, $selectedCollectibility)
            : null;
        $matchingLoanIds = $selectedRecap['ids'] ?? null;

        $mitraQuery = PumkMitra::query()
            ->withExists('pinjamanAktif')
            ->with([
                'wilayah:id,nama',
                'sektorUsaha:id,nama',
                'pinjaman' => fn ($query) => $query
                    ->with(['saldoAwal', 'angsuran', 'closures', 'classificationHistory'])
                    ->when($statusFilter === 'aktif', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true))
                    ->when($statusFilter === 'lunas', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_LUNAS))
                    ->when($matchingLoanIds !== null, fn ($loan) => $loan->whereIn('id', $matchingLoanIds))
                    ->latest('tanggal_pencairan')
                    ->latest('id'),
            ])
            ->when($statusFilter === 'aktif', fn ($query) => $query->whereHas('pinjamanAktif'))
            ->when($statusFilter === 'lunas', fn ($query) => $query
                ->whereDoesntHave('pinjamanAktif')
                ->whereHas('pinjaman', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_LUNAS)))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $keyword = trim((string) $filters['q']);
                $query->where('nama_mitra', 'like', '%'.$keyword.'%');
            })
            ->when(filled($filters['wilayah'] ?? null), fn ($query) => $query->where('wilayah_id', $filters['wilayah']))
            ->when(filled($filters['sektor'] ?? null), fn ($query) => $query->where('sektor_usaha_id', $filters['sektor']))
            ->when($matchingLoanIds !== null, function ($query) use ($matchingLoanIds, $statusFilter): void {
                $query->whereHas('pinjaman', fn ($pinjaman) => $pinjaman
                    ->whereIn('id', $matchingLoanIds)
                    ->when($statusFilter === 'aktif', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true))
                    ->when($statusFilter === 'lunas', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_LUNAS)));
            })
            ->orderBy('nama_mitra');

        $mitraList = $mitraQuery->paginate(15)->withQueryString();
        foreach ($mitraList as $mitra) {
            foreach ($mitra->pinjaman as $pinjaman) {
                $position = $collectibilitySummary->positionForLoan($pinjaman);
                $pinjaman->setAttribute('rekap_category', $position['category']);
                $pinjaman->setAttribute('rekap_balance', $position['balance']);
            }
        }

        return view('pumk-admin.mitra.index', [
            'mitraList' => $mitraList,
            'collectibilitySummary' => $selectedRecap['summary'] ?? null,
            'selectedCollectibility' => $selectedCollectibility,
            'wilayahList' => PumkWilayah::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
            'sektorList' => PumkSektorUsaha::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
            'collectibilityOptions' => $this->collectibilityOptions(),
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(): View
    {
        return view('pumk-admin.mitra.form', $this->formData(new PumkMitra, new PumkPinjaman));
    }

    public function show(Request $request, PumkMitra $mitra, KartuPiutangService $kartuPiutang, PumkLoanSettlementService $settlement): View
    {
        $mitra->load([
            'wilayah:id,nama',
            'sektorUsaha:id,nama',
            'pinjaman' => fn ($query) => $query
                ->with([
                    'saldoAwal',
                    'angsuran' => fn ($angsuran) => $angsuran->oldest('periode'),
                    'dokumenKontrak',
                ])
                ->latest('tanggal_pencairan')
                ->latest('id'),
        ]);

        /** @var PumkPinjaman|null $pinjaman */
        $requestedLoanId = $request->integer('pinjaman');
        $pinjaman = $requestedLoanId
            ? $mitra->pinjaman->firstWhere('id', $requestedLoanId)
            : ($mitra->pinjaman->firstWhere('status', PumkPinjaman::STATUS_AKTIF) ?? $mitra->pinjaman->first());
        abort_if($requestedLoanId && ! $pinjaman, 404);
        $tahun = PumkYearFilter::requestedYear($request) ?? 'terbaru';
        $kartu = $pinjaman ? $kartuPiutang->buat($pinjaman, tahun: $tahun) : null;
        $calculation = $kartu['calculation'] ?? null;
        $settlementPreview = $pinjaman?->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active
            ? $settlement->preview($pinjaman, card: $calculation)
            : null;
        $pinjamanList = $mitra->pinjaman;

        return view('pumk-admin.mitra.show', compact('mitra', 'pinjaman', 'pinjamanList', 'kartu', 'calculation', 'settlementPreview'));
    }

    public function edit(Request $request, PumkMitra $mitra): View
    {
        $request->validate(['new_loan' => ['prohibited']]);
        $mitra->load(['pinjaman' => fn ($query) => $query->with('dokumenKontrak')->latest('id')]);
        $id = $request->integer('pinjaman');
        $pinjaman = $id
            ? $mitra->pinjaman->firstWhere('id', $id)
            : ($mitra->pinjaman->first(fn ($loan) => $loan->status === PumkPinjaman::STATUS_AKTIF && $loan->is_active)
                ?? $mitra->pinjaman->first());
        abort_if(! $pinjaman, 404, 'Pinjaman tidak ditemukan.');

        return view('pumk-admin.mitra.form', $this->formData($mitra, $pinjaman));
    }

    private function formData(PumkMitra $mitra, PumkPinjaman $pinjaman): array
    {
        return [
            'mitra' => $mitra,
            'pinjaman' => $pinjaman,
            'wilayahList' => PumkWilayah::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
            'sektorList' => PumkSektorUsaha::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
            'documentTypes' => PumkPinjamanDokumen::TYPES,
            'contractDocuments' => $pinjaman->exists
                ? $pinjaman->dokumenKontrak->keyBy('jenis_dokumen')
                : collect(),
        ];
    }

    private function collectibilityValues(): array
    {
        return array_keys($this->collectibilityOptions());
    }

    private function collectibilityOptions(): array
    {
        return [
            'lancar' => 'Lancar',
            'kurang_lancar' => 'Kurang Lancar',
            'diragukan' => 'Diragukan',
            'macet' => 'Macet',
        ];
    }
}
