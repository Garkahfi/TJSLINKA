<?php

namespace App\Http\Controllers;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Services\Monitoring\PumkClassificationService;
use App\Services\Pumk\KartuPiutangService;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkActivityLogger;
use App\Services\Pumk\PumkLoanDocumentService;
use App\Services\Pumk\PumkLoanSettlementService;
use App\Services\Pumk\PumkPaymentProofService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkMitraController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'wilayah' => ['nullable', 'integer', 'exists:pumk_wilayah,id'],
            'sektor' => ['nullable', 'integer', 'exists:pumk_sektor_usaha,id'],
            'kolektibilitas' => ['nullable', Rule::in($this->collectibilityValues())],
            'status' => ['nullable', Rule::in(['aktif', 'lunas', 'semua'])],
        ]);
        $statusFilter = $filters['status'] ?? 'aktif';

        $mitraQuery = PumkMitra::query()
            ->withExists('pinjamanAktif')
            ->with([
                'wilayah:id,nama',
                'sektorUsaha:id,nama',
                'pinjaman' => fn ($query) => $query
                    ->select(['id', 'mitra_id', 'spj_awal', 'tanggal_pencairan', 'kolektibilitas', 'total_sisa', 'status', 'is_active', 'lunas_at'])
                    ->when($statusFilter === 'aktif', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true))
                    ->when($statusFilter === 'lunas', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_LUNAS))
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
            ->when(filled($filters['kolektibilitas'] ?? null), function ($query) use ($filters, $statusFilter): void {
                $query->whereHas('pinjaman', fn ($pinjaman) => $pinjaman
                    ->where('kolektibilitas', $filters['kolektibilitas'])
                    ->when($statusFilter === 'aktif', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true))
                    ->when($statusFilter === 'lunas', fn ($loan) => $loan->where('status', PumkPinjaman::STATUS_LUNAS)));
            })
            ->orderBy('nama_mitra');

        return view('pumk-admin.mitra.index', [
            'mitraList' => $mitraQuery->paginate(15)->withQueryString(),
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

    public function store(
        Request $request,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
    ): RedirectResponse {
        $data = $this->validateMitra($request);

        $existingArchived = PumkMitra::query()
            ->whereRaw('LOWER(nama_mitra) = ?', [mb_strtolower(trim($data['nama_mitra']))])
            ->whereDoesntHave('pinjamanAktif')
            ->first();
        if ($existingArchived) {
            return back()->withErrors([
                'nama_mitra' => 'Mitra dengan nama ini sudah ada di arsip. Aktifkan kembali dari daftar Mitra Lunas/Arsip agar histori tidak terduplikasi.',
            ])->withInput();
        }

        $mitra = DB::transaction(function () use ($request, $data, $calculator, $activity, $documents, $classifications): PumkMitra {
            $mitra = PumkMitra::create($this->mitraPayload($data) + [
                'source_key' => hash('sha256', 'manual-mitra|'.Str::uuid()),
                'created_by' => auth('pumk')->id(),
                'is_active' => true,
            ]);

            $pinjaman = $mitra->pinjaman()->create($this->pinjamanPayload($data) + [
                'source_key' => hash('sha256', 'manual-pinjaman|'.Str::uuid()),
                'created_by' => auth('pumk')->id(),
                'status' => PumkPinjaman::STATUS_AKTIF,
                'is_active' => true,
            ]);

            $calculator->sinkronkanCache($pinjaman);
            $effective = CarbonImmutable::now('Asia/Jakarta');
            $classifications->record($mitra, null, 'sektor', $mitra->sektor_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
            $classifications->record($mitra, null, 'wilayah', $mitra->wilayah_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
            $classifications->record(null, $pinjaman, 'kolektibilitas', $pinjaman->kolektibilitas, $effective, 'estimate', (string) Str::uuid(), auth('pumk')->id());
            $this->storeContractDocuments($request, $pinjaman, $documents);
            $activity->record('create_loan', 'pumk_internal', 'Menambahkan Mitra dan pinjaman baru.', $pinjaman);

            return $mitra;
        });

        return redirect()
            ->route('pumk-admin.mitra.show', $mitra)
            ->with('success', 'Mitra binaan berhasil ditambahkan.');
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
        $tahun = $this->requestedYear($request) ?? 'terbaru';
        $kartu = $pinjaman ? $kartuPiutang->buat($pinjaman, tahun: $tahun) : null;
        $calculation = $kartu['calculation'] ?? null;
        $settlementPreview = $pinjaman?->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active
            ? $settlement->preview($pinjaman)
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

    public function update(
        Request $request,
        PumkMitra $mitra,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
    ): RedirectResponse {
        $request->validate([
            'pinjaman_id' => ['nullable', 'integer'],
            'new_loan' => ['prohibited'],
            'edit_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $data = $this->validateMitra($request);
        $loanId = DB::transaction(function () use ($request, $data, $mitra, $calculator, $activity, $documents, $classifications): int {
            $lockedMitra = PumkMitra::query()->lockForUpdate()->findOrFail($mitra->id);
            $id = $request->integer('pinjaman_id');
            $pinjaman = $id
                ? $lockedMitra->pinjaman()->lockForUpdate()->findOrFail($id)
                : ($lockedMitra->pinjaman()->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true)->latest('id')->lockForUpdate()->first()
                    ?? $lockedMitra->pinjaman()->latest('id')->lockForUpdate()->first());
            abort_if($pinjaman === null, 422, 'Pinjaman tidak ditemukan.');
            $archiveEdit = $pinjaman !== null && ($pinjaman->status !== PumkPinjaman::STATUS_AKTIF || ! $pinjaman->is_active);
            $financialFields = ['tanggal_pencairan', 'mulai_angsuran', 'selesai_angsuran', 'pinjaman_pokok', 'persen_bunga', 'pinjaman_bunga', 'nilai_angsuran_bulanan'];
            if ($archiveEdit) {
                $request->validate(array_fill_keys($financialFields, ['prohibited']) + [
                    'edit_reason' => ['required', 'string', 'min:5', 'max:1000'],
                ]);
            }
            // A document-only request must never erase existing nominal values or identity fields.
            $identityPayload = $this->mitraPayload($data);
            $identityKeys = array_map(fn ($key) => match ($key) {
                'no_ktp' => 'no_ktp_encrypted', 'no_telepon' => 'no_telepon_encrypted', 'no_rekening' => 'no_rekening_encrypted',
                default => $key,
            }, array_keys($data));
            if (array_key_exists('sektor_usaha_id', $data)) {
                $identityKeys[] = 'sektor_sumber';
            }
            if (array_key_exists('wilayah_id', $data)) {
                $identityKeys[] = 'wilayah_sumber';
            }
            $lockedMitra->update(array_intersect_key($identityPayload, array_flip($identityKeys)));
            $sectorChanged = $lockedMitra->wasChanged(['sektor_sumber', 'sektor_usaha_id']);
            $regionChanged = $lockedMitra->wasChanged(['wilayah_sumber', 'wilayah_id']);
            $payload = array_intersect_key($this->pinjamanPayload($data), $data);
            if ($archiveEdit) {
                $payload = array_diff_key($payload, array_flip($financialFields));
            }
            $pinjaman->update($payload);
            $qualityBefore = $pinjaman->kolektibilitas;
            if (! $archiveEdit) {
                $calculator->sinkronkanCache($pinjaman);
            }
            $effective = CarbonImmutable::now('Asia/Jakarta');
            if ($sectorChanged) {
                $classifications->record($lockedMitra, null, 'sektor', $lockedMitra->sektor_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
            }
            if ($regionChanged) {
                $classifications->record($lockedMitra, null, 'wilayah', $lockedMitra->wilayah_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
            }
            if (! $archiveEdit && $qualityBefore !== $pinjaman->fresh()->kolektibilitas) {
                $classifications->record(null, $pinjaman, 'kolektibilitas', $pinjaman->fresh()->kolektibilitas, $effective, 'estimate', (string) Str::uuid(), auth('pumk')->id());
            }
            $this->storeContractDocuments($request, $pinjaman, $documents);
            $activity->record($archiveEdit ? 'edit_archived_loan' : 'update_loan',
                'pumk_internal', $archiveEdit ? 'Memperbarui administrasi arsip; status dan saldo tetap.' : 'Memperbarui data Mitra atau fasilitas pinjaman.',
                $pinjaman, metadata: ['reason' => $request->input('edit_reason')]);

            return $pinjaman->id;
        });

        return redirect()->route('pumk-admin.mitra.show', $request->filled('pinjaman_id') ? [$mitra, 'pinjaman' => $loanId] : $mitra)
            ->with('success', 'Data administrasi dan dokumen berhasil disimpan.');
    }

    public function reopen(Request $request, PumkMitra $mitra, PumkPinjaman $pinjaman, PumkLoanSettlementService $settlement): RedirectResponse
    {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        $data = $request->validate(['reopen_note' => ['required', 'string', 'min:5', 'max:1000']]);
        $settlement->reopen($mitra->id, $pinjaman->id, $data['reopen_note'], (int) auth('pumk')->id());

        return redirect()->route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman->id])
            ->with('success', 'Pinjaman lama dibuka kembali dengan saldo dan histori yang sama. Tidak ada pinjaman baru.');
    }

    public function storeAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        abort_unless($pinjaman->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active, 409, 'Pinjaman yang sudah selesai tidak dapat menerima angsuran baru.');
        $data = $this->validateAngsuran($request);

        $periode = CarbonImmutable::createFromFormat('!Y-m', $data['periode'])->startOfMonth();
        $alreadyExists = $pinjaman->angsuran()->whereDate('periode', $periode)->exists();

        if ($alreadyExists) {
            return back()
                ->withErrors(['periode' => 'Angsuran untuk periode tersebut sudah tercatat.'])
                ->withInput();
        }

        $confirmed = $request->boolean('hapus_saldo_awal');
        $saldoAwal = $pinjaman->saldoAwal()->first();
        if ($saldoAwal !== null
            && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)
            && ! $confirmed) {
            return $this->saldoAwalConflictResponse(
                $request,
                $saldoAwal->cutoff_date,
                $periode,
                route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]),
                'POST',
            );
        }

        DB::transaction(function () use ($data, $periode, $mitra, $pinjaman, $calculator, $confirmed, $request, $proofs): void {
            PumkMitra::query()->lockForUpdate()->findOrFail($mitra->id);
            $lockedLoan = PumkPinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            abort_unless($lockedLoan->status === PumkPinjaman::STATUS_AKTIF && $lockedLoan->is_active, 409, 'Pinjaman yang sudah selesai tidak dapat menerima angsuran baru.');
            abort_if($lockedLoan->angsuran()->whereDate('periode', $periode)->exists(), 409, 'Angsuran periode ini sudah tercatat.');
            $saldoAwal = $lockedLoan->saldoAwal()->lockForUpdate()->first();

            if ($saldoAwal !== null && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)) {
                abort_unless($confirmed, 409, 'Saldo Awal berubah. Muat ulang halaman lalu konfirmasi kembali.');
                $saldoAwal->delete();
                $calculator->bangunUlangBaselineTanpaSaldoAwal($lockedLoan);
            }

            $angsuran = PumkAngsuran::create([
                'pinjaman_id' => $lockedLoan->id,
                'periode' => $periode,
                'nomor_bukti' => filled($data['nomor_bukti'] ?? null) ? trim($data['nomor_bukti']) : null,
                'pokok' => $data['pokok'],
                'bunga' => $data['bunga'],
                'denda' => $data['denda'] ?? 0,
                'created_by' => auth('pumk')->id(),
            ]);
            if ($request->hasFile('bukti_pembayaran')) {
                $proofs->replace($angsuran, $request->file('bukti_pembayaran'));
            }
        });

        app(PumkActivityLogger::class)->record('create_installment', 'pumk_internal', 'Menambahkan angsuran manual.', $pinjaman, metadata: ['periode' => $periode->format('Y-m')]);

        return redirect()
            ->route('pumk-admin.mitra.show', $mitra)
            ->with('success', 'Angsuran berhasil ditambahkan dan perhitungan kartu piutang telah diperbarui.');
    }

    public function updateAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        abort_unless($pinjaman->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active, 409, 'Angsuran pada pinjaman yang sudah selesai tidak dapat diubah.');
        abort_unless($angsuran->pinjaman_id === $pinjaman->id, 404);

        // Data yang berasal dari workbook adalah data sumber. Koreksi terhadap
        // data tersebut harus dilakukan lewat proses impor, bukan form manual.
        abort_if(
            $angsuran->batch_id !== null || $angsuran->created_by === null,
            403,
            'Angsuran hasil impor tidak dapat diedit dari kartu piutang.',
        );

        $data = $this->validateAngsuran($request);
        $periode = CarbonImmutable::createFromFormat('!Y-m', $data['periode'])->startOfMonth();
        $alreadyExists = $pinjaman->angsuran()
            ->whereKeyNot($angsuran->id)
            ->whereDate('periode', $periode)
            ->exists();

        if ($alreadyExists) {
            return back()
                ->withErrors(['periode' => 'Angsuran untuk periode tersebut sudah tercatat.'])
                ->withInput();
        }

        $confirmed = $request->boolean('hapus_saldo_awal');
        $saldoAwal = $pinjaman->saldoAwal()->first();
        if ($saldoAwal !== null
            && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)
            && ! $confirmed) {
            return $this->saldoAwalConflictResponse(
                $request,
                $saldoAwal->cutoff_date,
                $periode,
                route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $angsuran]),
                'PATCH',
            );
        }

        DB::transaction(function () use ($angsuran, $data, $periode, $mitra, $pinjaman, $calculator, $confirmed, $request, $proofs): void {
            PumkMitra::query()->lockForUpdate()->findOrFail($mitra->id);
            $lockedLoan = PumkPinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            abort_unless($lockedLoan->status === PumkPinjaman::STATUS_AKTIF && $lockedLoan->is_active, 409, 'Angsuran pada pinjaman yang sudah selesai tidak dapat diubah.');
            $lockedPayment = $lockedLoan->angsuran()->lockForUpdate()->findOrFail($angsuran->id);
            abort_if($lockedPayment->batch_id !== null || $lockedPayment->created_by === null, 403,
                'Angsuran hasil impor tidak dapat diedit dari kartu piutang.');
            abort_if($lockedLoan->angsuran()->whereKeyNot($lockedPayment->id)->whereDate('periode', $periode)->exists(), 409, 'Angsuran periode ini sudah tercatat.');
            $saldoAwal = $lockedLoan->saldoAwal()->lockForUpdate()->first();

            if ($saldoAwal !== null && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)) {
                abort_unless($confirmed, 409, 'Saldo Awal berubah. Muat ulang halaman lalu konfirmasi kembali.');
                $saldoAwal->delete();
                $calculator->bangunUlangBaselineTanpaSaldoAwal($lockedLoan);
            }

            $lockedPayment->update([
                'periode' => $periode,
                'nomor_bukti' => filled($data['nomor_bukti'] ?? null) ? trim($data['nomor_bukti']) : null,
                'pokok' => $data['pokok'],
                'bunga' => $data['bunga'],
                'denda' => $data['denda'],
            ]);
            if ($request->hasFile('bukti_pembayaran')) {
                $proofs->replace($lockedPayment->fresh(), $request->file('bukti_pembayaran'));
            }
        });

        app(PumkActivityLogger::class)->record('update_installment', 'pumk_internal', 'Memperbarui angsuran manual.', $pinjaman, metadata: ['periode' => $periode->format('Y-m')]);

        return redirect()
            ->route('pumk-admin.mitra.show', $mitra)
            ->with('success', 'Angsuran berhasil diperbarui dan saldo kartu piutang telah dihitung ulang.');
    }

    public function markPaid(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkLoanSettlementService $settlement,
    ): RedirectResponse {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        $data = $request->validate([
            'lunas_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $result = $settlement->settle(
            $mitra->id, $pinjaman->id, $data['lunas_note'] ?? null,
            (int) auth('pumk')->id(),
        );

        return redirect()->route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman->id])
            ->with('success', $result['status'] === 'already_paid'
                ? 'Pinjaman sudah berstatus lunas; catatan penutupan pertama tetap tersimpan.'
                : 'Pinjaman berhasil ditandai lunas. Seluruh histori tetap tersimpan.');
    }

    public function exportExcel(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        $data = $this->kartuExportData($mitra, $pinjaman, $kartuPiutang, $this->requestedYear($request) ?? 'terbaru');
        $filename = 'kartu-piutang-'.Str::slug($mitra->nama_mitra).'-'.$this->yearFileLabel($data['kartu']['tahun_terpilih']).'.xls';

        return response()
            ->view('pumk-admin.mitra.exports.excel', $data)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function exportPdf(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        $data = $this->kartuExportData($mitra, $pinjaman, $kartuPiutang, $this->requestedYear($request) ?? 'terbaru');
        $logoPath = public_path('images/logo/inka.png');
        $data['logoDataUri'] = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pumk-admin.mitra.exports.pdf', $data)->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        $filename = 'kartu-piutang-'.Str::slug($mitra->nama_mitra).'-'.$this->yearFileLabel($data['kartu']['tahun_terpilih']).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function viewDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        $this->ensureDocumentBelongsToLoan($mitra, $pinjaman, $document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->response($document->file_path, $document->nama_file_asli, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $document->nama_file_asli).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function downloadDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        $this->ensureDocumentBelongsToLoan($mitra, $pinjaman, $document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_file_asli, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroyDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
        PumkLoanDocumentService $documents,
    ): RedirectResponse {
        $this->ensureDocumentBelongsToLoan($mitra, $pinjaman, $document);
        $documents->delete($document);

        return back()->with('success', 'Dokumen kontrak berhasil dihapus.');
    }

    public function viewPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        $this->ensureInstallmentBelongsToLoan($mitra, $pinjaman, $angsuran);
        $disk = Storage::disk('local');
        abort_unless(filled($angsuran->bukti_pembayaran_path) && $disk->exists($angsuran->bukti_pembayaran_path), 404);

        return $disk->response(
            $angsuran->bukti_pembayaran_path,
            $angsuran->bukti_pembayaran_nama_asli,
            [
                'Content-Type' => $angsuran->bukti_pembayaran_mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    public function downloadPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        $this->ensureInstallmentBelongsToLoan($mitra, $pinjaman, $angsuran);
        $disk = Storage::disk('local');
        abort_unless(filled($angsuran->bukti_pembayaran_path) && $disk->exists($angsuran->bukti_pembayaran_path), 404);

        return $disk->download(
            $angsuran->bukti_pembayaran_path,
            $angsuran->bukti_pembayaran_nama_asli,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'],
        );
    }

    public function destroyPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        $this->ensureInstallmentBelongsToLoan($mitra, $pinjaman, $angsuran);
        abort_unless($pinjaman->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active, 409, 'Bukti pada pinjaman yang sudah selesai tidak dapat diubah.');
        $proofs->delete($angsuran);

        return back()->with('success', 'Bukti pembayaran berhasil dihapus. Data angsuran tetap tersimpan.');
    }

    /** @return array<string, mixed> */
    private function validateAngsuran(Request $request): array
    {
        $request->merge([
            'pokok' => $this->normalizeRupiah($request->input('pokok')),
            'bunga' => $this->normalizeRupiah($request->input('bunga')),
            'denda' => $this->normalizeRupiah($request->input('denda'), true),
        ]);

        return $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
            'nomor_bukti' => ['nullable', 'string', 'max:255'],
            'pokok' => ['required', 'numeric', 'min:0'],
            'bunga' => ['required', 'numeric', 'min:0'],
            'denda' => ['required', 'numeric', 'min:0'],
            'bukti_pembayaran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.config('pumk.payment_proof_max_kb')],
            'hapus_saldo_awal' => ['nullable', 'boolean'],
        ], [
            'pokok.numeric' => 'Pokok harus berupa nominal Rupiah.',
            'bunga.numeric' => 'Bunga harus berupa nominal Rupiah atau tanda - jika tidak ada.',
            'denda.numeric' => 'Denda harus berupa nominal Rupiah atau tanda - jika tidak ada.',
        ]);
    }

    private function normalizeRupiah(mixed $value, bool $emptyAsZero = false): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '-' || ($emptyAsZero && $value === '')) {
            return '0';
        }

        if ($value === '') {
            return $value;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits === '' ? $value : $digits;
    }

    private function periodeTumpangTindihSaldoAwal(
        CarbonImmutable $periode,
        ?CarbonInterface $cutoffDate,
    ): bool {
        return $cutoffDate !== null
            && $periode->startOfMonth()->lessThanOrEqualTo(CarbonImmutable::instance($cutoffDate)->startOfMonth());
    }

    private function saldoAwalConflictResponse(
        Request $request,
        CarbonInterface $cutoffDate,
        CarbonImmutable $periode,
        string $action,
        string $method,
    ): RedirectResponse {
        $cutoff = CarbonImmutable::instance($cutoffDate);
        $message = 'Pinjaman ini memiliki Saldo Awal per '.$cutoff->translatedFormat('d F Y').'. '
            .'Angsuran periode '.$periode->translatedFormat('F Y').' tumpang tindih dengan akumulasi tersebut. '
            .'Jika dilanjutkan, Saldo Awal akan dihapus agar pembayaran tidak dihitung ganda.';

        return back()
            ->withErrors(['periode' => $message])
            ->withInput()
            ->with('saldo_awal_overlap', [
                'action' => $action,
                'method' => $method,
                'cutoff' => $cutoff->translatedFormat('d F Y'),
                'periode' => $periode->translatedFormat('F Y'),
                'proof_was_uploaded' => $request->hasFile('bukti_pembayaran'),
            ]);
    }

    private function ensureLoanBelongsToMitra(PumkMitra $mitra, PumkPinjaman $pinjaman): void
    {
        abort_unless($pinjaman->mitra_id === $mitra->id, 404);
    }

    private function ensureDocumentBelongsToLoan(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): void {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        abort_unless($document->pinjaman_id === $pinjaman->id, 404);
    }

    private function ensureInstallmentBelongsToLoan(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): void {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        abort_unless($angsuran->pinjaman_id === $pinjaman->id, 404);
    }

    /** @return array<string, mixed> */
    private function kartuExportData(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
        int|string|null $tahun = null,
    ): array {
        $this->ensureLoanBelongsToMitra($mitra, $pinjaman);
        $mitra->loadMissing(['wilayah:id,nama', 'sektorUsaha:id,nama']);
        $pinjaman->load([
            'saldoAwal',
            'angsuran' => fn ($query) => $query->oldest('periode'),
        ]);

        return [
            'mitra' => $mitra,
            'pinjaman' => $pinjaman,
            'kartu' => $kartuPiutang->buat($pinjaman, tahun: $tahun),
            'generatedAt' => now(),
        ];
    }

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
    private function validateMitra(Request $request): array
    {
        $contractDocumentMaxMb = (int) ceil((int) config('pumk.contract_document_max_kb') / 1024);

        return $request->validate([
            'nama_mitra' => ['required', 'string', 'max:255'],
            'jenis_usaha' => ['nullable', 'string', 'max:255'],
            'sektor_usaha_id' => ['nullable', 'integer', 'exists:pumk_sektor_usaha,id'],
            'wilayah_id' => ['nullable', 'integer', 'exists:pumk_wilayah,id'],
            'alamat' => ['nullable', 'string', 'max:3000'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'no_ktp' => ['nullable', 'string', 'max:64'],
            'no_telepon' => ['nullable', 'string', 'max:64'],
            'no_rekening' => ['nullable', 'string', 'max:64'],
            'spj_awal' => ['nullable', 'string', 'max:255'],
            'reschedule_ke1' => ['nullable', 'string', 'max:255'],
            'reschedule_ke2' => ['nullable', 'string', 'max:255'],
            'reschedule_ke3' => ['nullable', 'string', 'max:255'],
            'reschedule_ke4' => ['nullable', 'string', 'max:255'],
            'jenis_jaminan' => ['nullable', 'string', 'max:3000'],
            'jaminan_no_pol' => ['nullable', 'string', 'max:255'],
            'jaminan_no_bpkb' => ['nullable', 'string', 'max:255'],
            'jaminan_merk' => ['nullable', 'string', 'max:255'],
            'jaminan_type' => ['nullable', 'string', 'max:255'],
            'jaminan_tahun_kendaraan' => ['nullable', 'string', 'max:255'],
            'jaminan_no_sertifikat' => ['nullable', 'string', 'max:255'],
            'jaminan_luas' => ['nullable', 'string', 'max:255'],
            'jaminan_atas_nama' => ['nullable', 'string', 'max:255'],
            'jaminan_alamat' => ['nullable', 'string', 'max:3000'],
            'tanggal_pencairan' => ['nullable', 'date'],
            'mulai_angsuran' => ['nullable', 'date'],
            'selesai_angsuran' => ['nullable', 'date', 'after_or_equal:mulai_angsuran'],
            'pinjaman_pokok' => ['nullable', 'numeric', 'min:0'],
            'persen_bunga' => ['nullable', 'numeric', 'min:0'],
            'pinjaman_bunga' => ['nullable', 'numeric', 'min:0'],
            'nilai_angsuran_bulanan' => ['nullable', 'numeric', 'min:0'],
            'dokumen_spj_awal' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_1' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_2' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_3' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_4' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
        ], [
            'dokumen_spj_awal.max' => "Ukuran Dokumen SPJ Awal maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_1.max' => "Ukuran Dokumen Reschedule Ke-1 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_2.max' => "Ukuran Dokumen Reschedule Ke-2 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_3.max' => "Ukuran Dokumen Reschedule Ke-3 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_4.max' => "Ukuran Dokumen Reschedule Ke-4 maksimal {$contractDocumentMaxMb} MB.",
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mitraPayload(array $data): array
    {
        $sektor = filled($data['sektor_usaha_id'] ?? null)
            ? PumkSektorUsaha::find($data['sektor_usaha_id'])
            : null;
        $wilayah = filled($data['wilayah_id'] ?? null)
            ? PumkWilayah::find($data['wilayah_id'])
            : null;

        return [
            'nama_mitra' => trim($data['nama_mitra']),
            'jenis_usaha' => $data['jenis_usaha'] ?? null,
            'sektor_usaha_id' => $sektor?->id,
            'sektor_sumber' => $sektor?->nama,
            'wilayah_id' => $wilayah?->id,
            'wilayah_sumber' => $wilayah?->nama,
            'alamat' => $data['alamat'] ?? null,
            'nama_pemilik' => $data['nama_pemilik'] ?? null,
            'no_ktp_encrypted' => $data['no_ktp'] ?? null,
            'no_telepon_encrypted' => $data['no_telepon'] ?? null,
            'no_rekening_encrypted' => $data['no_rekening'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pinjamanPayload(array $data): array
    {
        return [
            'spj_awal' => $data['spj_awal'] ?? null,
            'reschedule_ke1' => $data['reschedule_ke1'] ?? null,
            'reschedule_ke2' => $data['reschedule_ke2'] ?? null,
            'reschedule_ke3' => $data['reschedule_ke3'] ?? null,
            'reschedule_ke4' => $data['reschedule_ke4'] ?? null,
            'jenis_jaminan' => $data['jenis_jaminan'] ?? null,
            'jaminan_no_pol' => $data['jaminan_no_pol'] ?? null,
            'jaminan_no_bpkb' => $data['jaminan_no_bpkb'] ?? null,
            'jaminan_merk' => $data['jaminan_merk'] ?? null,
            'jaminan_type' => $data['jaminan_type'] ?? null,
            'jaminan_tahun_kendaraan' => $data['jaminan_tahun_kendaraan'] ?? null,
            'jaminan_no_sertifikat' => $data['jaminan_no_sertifikat'] ?? null,
            'jaminan_luas' => $data['jaminan_luas'] ?? null,
            'jaminan_atas_nama' => $data['jaminan_atas_nama'] ?? null,
            'jaminan_alamat' => $data['jaminan_alamat'] ?? null,
            'tanggal_pencairan' => $data['tanggal_pencairan'] ?? null,
            'mulai_angsuran' => $data['mulai_angsuran'] ?? null,
            'selesai_angsuran' => $data['selesai_angsuran'] ?? null,
            'pinjaman_pokok' => $data['pinjaman_pokok'] ?? null,
            'persen_bunga' => $data['persen_bunga'] ?? null,
            'pinjaman_bunga' => $data['pinjaman_bunga'] ?? null,
            'nilai_angsuran_bulanan' => $data['nilai_angsuran_bulanan'] ?? null,
        ];
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

    private function yearFileLabel(int|string $year): string
    {
        return $year === 'semua' ? 'semua-tahun' : (string) $year;
    }

    private function storeContractDocuments(
        Request $request,
        PumkPinjaman $pinjaman,
        PumkLoanDocumentService $documents,
    ): void {
        foreach (array_keys(PumkPinjamanDokumen::TYPES) as $type) {
            $file = $request->file("dokumen_{$type}");
            if ($file !== null) {
                $documents->replace($pinjaman, $type, $file);
            }
        }
    }

    /** @return array<int, string> */
    private function collectibilityValues(): array
    {
        return array_keys($this->collectibilityOptions());
    }

    /** @return array<string, string> */
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
