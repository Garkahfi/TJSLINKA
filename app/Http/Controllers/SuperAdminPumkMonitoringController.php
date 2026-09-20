<?php

namespace App\Http\Controllers;

use App\Models\PumkActivityLog;
use App\Models\PumkAngsuran;
use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\PumkBriSnapshotBulanan;
use App\Models\PumkImportBatch;
use App\Models\PumkImportRow;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Services\Pumk\KartuPiutangService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminPumkMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['internal', 'bri'])],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
        ]);
        $tab = $filters['tab'] ?? 'internal';

        $years = PumkBriSnapshotBulanan::query()->distinct()->pluck('tahun')
            ->merge(PumkBriRkaTahunan::query()->pluck('tahun'))
            ->merge(PumkBriPenyaluranBulanan::query()->pluck('tahun'))
            ->map(fn (mixed $year): int => (int) $year)->unique()->sortDesc()->values();
        $year = (int) ($filters['tahun'] ?? $years->first() ?? now()->year);
        $rka = PumkBriRkaTahunan::query()->where('tahun', $year)->first();
        $realisasi = (float) PumkBriPenyaluranBulanan::query()->where('tahun', $year)->sum('nominal_penyaluran');
        $latestMonth = PumkBriSnapshotBulanan::query()->where('tahun', $year)->max('bulan');
        $latestSnapshots = PumkBriSnapshotBulanan::query()
            ->where('tahun', $year)
            ->when($latestMonth !== null, fn ($query) => $query->where('bulan', $latestMonth), fn ($query) => $query->whereRaw('1 = 0'));

        return view('superadmin.pumk.index', [
            'tab' => $tab,
            'totalMitra' => PumkMitra::whereHas('pinjamanAktif')->count(),
            'totalPinjaman' => PumkPinjaman::where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true)->count(),
            'totalMitraLunas' => PumkMitra::whereDoesntHave('pinjamanAktif')->whereHas('pinjaman', fn ($query) => $query->where('status', PumkPinjaman::STATUS_LUNAS))->count(),
            'totalPinjamanLunas' => PumkPinjaman::where('status', PumkPinjaman::STATUS_LUNAS)->count(),
            'totalAngsuranManual' => PumkAngsuran::whereNull('batch_id')->whereNotNull('created_by')->count(),
            'mitraBelumLengkap' => PumkMitra::whereHas('pinjamanAktif')
                ->where(fn ($query) => $query->whereNull('wilayah_id')->orWhereNull('sektor_usaha_id')->orWhereNull('nama_pemilik'))
                ->count(),
            'needsReview' => PumkImportRow::where('status', 'needs_review')->count(),
            'angsuranTerbaru' => PumkAngsuran::with(['creator:id,name', 'pinjaman.mitra:id,nama_mitra'])
                ->whereNull('batch_id')->whereNotNull('created_by')->latest()->limit(10)->get(),
            'pinjamanLunasTerbaru' => PumkPinjaman::with(['mitra:id,nama_mitra', 'pelunas:id,name'])
                ->where('status', PumkPinjaman::STATUS_LUNAS)->latest('lunas_at')->limit(10)->get(),
            'aktivitasTerbaru' => PumkActivityLog::with('actor:id,name')->latest()->limit(15)->get(),
            'importTerbaru' => PumkImportBatch::with('importer:id,name')
                ->withCount(['rows as needs_review_count' => fn ($query) => $query->where('status', 'needs_review')])
                ->latest()->limit(8)->get(),
            'years' => $years,
            'year' => $year,
            'rka' => $rka,
            'realisasi' => $realisasi,
            'progress' => $rka !== null && (float) $rka->nominal_rka > 0 ? round($realisasi / (float) $rka->nominal_rka * 100, 2) : null,
            'latestMonth' => $latestMonth,
            'briOutstanding' => $latestMonth === null ? null : (float) (clone $latestSnapshots)->sum('saldo_piutang'),
            'briTotalMitra' => $latestMonth === null ? null : (clone $latestSnapshots)->whereNotNull('mitra_id')->distinct()->count('mitra_id'),
            'briUnverified' => PumkBriSnapshotBulanan::where('tahun', $year)->where('profil_sumber_terverifikasi', false)->count(),
            'briIdentityReviews' => PumkBriIdentityReview::where('tahun', $year)->where('status', 'pending')->count(),
            'penyaluranBriTerbaru' => PumkBriPenyaluranBulanan::with('pengubah:id,name')->where('tahun', $year)->latest('updated_at')->limit(12)->get(),
        ]);
    }

    public function mitra(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['aktif', 'lunas', 'semua'])],
        ]);
        $status = $filters['status'] ?? 'aktif';

        $query = PumkMitra::query()
            ->withExists('pinjamanAktif')
            ->with(['wilayah:id,nama', 'sektorUsaha:id,nama', 'pinjaman' => fn ($loan) => $loan
                ->select(['id', 'mitra_id', 'tanggal_pencairan', 'pinjaman_pokok', 'kolektibilitas', 'total_sisa', 'status', 'is_active', 'lunas_at'])
                ->when($status === 'aktif', fn ($item) => $item->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true))
                ->when($status === 'lunas', fn ($item) => $item->where('status', PumkPinjaman::STATUS_LUNAS))
                ->latest('tanggal_pencairan')->latest('id')])
            ->when($status === 'aktif', fn ($item) => $item->whereHas('pinjamanAktif'))
            ->when($status === 'lunas', fn ($item) => $item->whereDoesntHave('pinjamanAktif')->whereHas('pinjaman', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_LUNAS)))
            ->when(filled($filters['q'] ?? null), fn ($item) => $item->where('nama_mitra', 'like', '%'.trim((string) $filters['q']).'%'))
            ->orderBy('nama_mitra');

        return view('superadmin.pumk.mitra', [
            'mitraList' => $query->paginate(15)->withQueryString(),
            'statusFilter' => $status,
        ]);
    }

    public function kartu(Request $request, PumkMitra $mitra, KartuPiutangService $service): View
    {
        $mitra->load([
            'wilayah:id,nama',
            'sektorUsaha:id,nama',
            'pinjaman' => fn ($query) => $query->with([
                'angsuran', 'saldoAwal', 'pelunas:id,name', 'dokumenKontrak',
            ])->latest('tanggal_pencairan')->latest('id'),
        ]);
        $requestedLoanId = $request->integer('pinjaman');
        $pinjaman = $requestedLoanId
            ? $mitra->pinjaman->firstWhere('id', $requestedLoanId)
            : ($mitra->pinjaman->firstWhere('status', PumkPinjaman::STATUS_AKTIF) ?? $mitra->pinjaman->first());
        abort_if($requestedLoanId && ! $pinjaman, 404);

        return view('superadmin.pumk.kartu', [
            'mitra' => $mitra,
            'pinjaman' => $pinjaman,
            'pinjamanList' => $mitra->pinjaman,
            'kartu' => $pinjaman ? $service->buat($pinjaman, tahun: $this->requestedYear($request) ?? 'terbaru') : null,
        ]);
    }

    private function requestedYear(Request $request): int|string|null
    {
        $value = $request->query('tahun');
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === 'semua') {
            return 'semua';
        }
        abort_unless(is_string($value) && preg_match('/^\d{4}$/', $value) === 1, 422, 'Filter tahun tidak valid.');

        $year = (int) $value;
        abort_unless($year >= 1900 && $year <= 2100, 422, 'Filter tahun tidak valid.');

        return $year;
    }
}
