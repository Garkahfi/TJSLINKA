<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Services\Monitoring\PumkClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PumkMitraChanges
{
    public function __construct(private readonly PumkMitraInput $input) {}

    public function store(
        Request $request,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
    ): RedirectResponse {
        $data = $this->input->validateMitra($request);

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
            $mitra = PumkMitra::create($this->input->mitraPayload($data) + [
                'source_key' => hash('sha256', 'manual-mitra|'.Str::uuid()),
                'created_by' => auth('pumk')->id(),
                'is_active' => true,
            ]);

            $pinjaman = $mitra->pinjaman()->create($this->input->pinjamanPayload($data) + [
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
        $data = $this->input->validateMitra($request);
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
            $identityPayload = $this->input->mitraPayload($data);
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
            $payload = array_intersect_key($this->input->pinjamanPayload($data), $data);
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
}
