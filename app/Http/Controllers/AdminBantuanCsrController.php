<?php

namespace App\Http\Controllers;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Models\Pillar;
use App\Models\StatusLog;
use App\Services\SubmissionNotificationService;
use App\Support\TjslSubmissionStatusFilter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdminBantuanCsrController extends Controller
{
    public function index(Request $request): View
    {
        $adminId = $request->user()->id;
        $statusFilter = TjslSubmissionStatusFilter::selected($request, true);

        return view('admin.assistance.index', [
            'bantuanPerPilar' => Pillar::query()
                ->orderBy('id')
                ->with(['bantuanCsr' => fn ($query) => $query
                    ->where('created_by', $adminId)
                    ->where('is_archived', false)
                    ->tap(fn ($query) => TjslSubmissionStatusFilter::apply($query, $statusFilter, true))
                    ->latest()])
                ->get(),
            'bantuanTanpaPilar' => BantuanCsr::where('created_by', $adminId)
                ->where('is_archived', false)
                ->tap(fn ($query) => TjslSubmissionStatusFilter::apply($query, $statusFilter, true))
                ->whereNull('pillar_id')
                ->latest()
                ->get(),
            'statusFilter' => $statusFilter,
            'statusOptions' => TjslSubmissionStatusFilter::options(true),
        ]);
    }

    public function create(): View
    {
        return view('admin.assistance.form', [
            'item' => new BantuanCsr,
            'pillars' => Pillar::orderBy('id')->get(),
            'editable' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $submit = $request->input('action') === 'submit';

        $item = DB::transaction(function () use ($request, $data, $submit) {
            $item = BantuanCsr::create([
                ...$data,
                'created_by' => $request->user()->id,
                'status' => 'draft',
                'submitted_at' => null,
            ]);

            $this->syncChildren($request, $item);
            $this->syncPhaseOneDocuments($request, $item);

            if ($submit && $this->phaseOneDocumentErrors($item) === []) {
                $item->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            $this->log($item, null, $item->status, $request->user()->id);

            return $item;
        });

        $documentErrors = $submit ? $this->phaseOneDocumentErrors($item) : [];

        if ($documentErrors !== []) {
            return redirect()
                ->route('admin.assistance.show', $item)
                ->withErrors($documentErrors)
                ->with('success', 'Data dan dokumen yang valid sudah disimpan sebagai draft. Lengkapi dokumen yang masih kurang.');
        }

        if ($item->status === 'pending_fase1') {
            app(SubmissionNotificationService::class)->assistanceSubmitted($item, 1);
        }

        return redirect()
            ->route('admin.assistance.show', $item)
            ->with('success', 'Bantuan TJSL berhasil disimpan.');
    }

    public function show(Request $request, BantuanCsr $bantuanCsr): View
    {
        $this->own($request, $bantuanCsr);

        return view('admin.assistance.form', [
            'item' => $bantuanCsr->load(
                'documents',
                'targets',
                'details.photos',
                'photos',
            ),
            'pillars' => Pillar::orderBy('id')->get(),
            'editable' => $bantuanCsr->canBeEdited(),
        ]);
    }

    public function phaseTwo(Request $request, BantuanCsr $bantuanCsr): View
    {
        $this->own($request, $bantuanCsr);
        abort_unless(
            ! $bantuanCsr->is_archived
            && in_array($bantuanCsr->status, ['approved_fase1', 'pending_fase2', 'completed'], true),
            403,
        );

        $bantuanCsr->load('documents', 'photos');

        return view('admin.assistance.phase-two', [
            'item' => $bantuanCsr,
            'bastDocument' => $bantuanCsr->documents->firstWhere('document_type', 'bast'),
            'legacyAdditionalDocuments' => $bantuanCsr->documents
                ->where('document_type', BantuanCsr::PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE)
                ->values(),
            'editable' => $bantuanCsr->canUploadBast(),
        ]);
    }

    public function update(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        $this->own($request, $bantuanCsr);
        abort_unless($bantuanCsr->canBeEdited(), 403);

        $data = $this->data($request);
        $submit = $request->input('action') === 'submit';
        $from = $bantuanCsr->status;

        DB::transaction(function () use ($request, $bantuanCsr, $data, $submit, $from) {
            $bantuanCsr->update([
                ...$data,
                'status' => 'draft',
                'submitted_at' => null,
                'fase1_rejected_reason' => null,
            ]);

            $this->replaceChildren($request, $bantuanCsr);
            $this->syncPhaseOneDocuments($request, $bantuanCsr);

            if ($submit && $this->phaseOneDocumentErrors($bantuanCsr) === []) {
                $bantuanCsr->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            if ($from !== $bantuanCsr->status) {
                $this->log(
                    $bantuanCsr,
                    $from,
                    $bantuanCsr->status,
                    $request->user()->id,
                );
            }
        });

        $documentErrors = $submit ? $this->phaseOneDocumentErrors($bantuanCsr) : [];

        if ($documentErrors !== []) {
            return back()
                ->withErrors($documentErrors)
                ->with('success', 'Data dan dokumen yang valid sudah disimpan. Lengkapi dokumen yang masih kurang.');
        }

        if ($bantuanCsr->status === 'pending_fase1') {
            app(SubmissionNotificationService::class)->assistanceSubmitted($bantuanCsr, 1);
        }

        return back()->with('success', 'Perubahan bantuan tersimpan.');
    }

    public function cancel(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        $this->own($request, $bantuanCsr);
        abort_unless($bantuanCsr->status === 'pending_fase1', 403);

        $bantuanCsr->update([
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        $this->log(
            $bantuanCsr,
            'pending_fase1',
            'draft',
            $request->user()->id,
            'Pengajuan dibatalkan Admin',
        );

        return redirect()->route('admin.assistance.show', $bantuanCsr);
    }

    public function uploadBast(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        $this->own($request, $bantuanCsr);
        abort_unless($bantuanCsr->canUploadBast(), 403);

        $validated = $request->validate([
            'dokumen_f' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
            'bast_document_name' => ['nullable', 'string', 'max:255'],
            'phase2_photos' => ['nullable', 'array', 'max:10'],
            'phase2_photos.*' => ['image', 'max:20480'],
            'phase2_photo_captions' => ['nullable', 'array', 'max:10'],
            'phase2_photo_captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $validated['dokumen_f'];
        $newLocalPaths = [];
        $newPublicPaths = [];
        $photos = [];
        $photoCaptions = $validated['phase2_photo_captions'] ?? [];

        $oldDocuments = $bantuanCsr->documents()
            ->where('document_type', 'bast')
            ->get();

        try {
            $path = $file->store(
                "bantuan-csr/{$bantuanCsr->id}/documents",
                'local',
            );
            $newLocalPaths[] = $path;

            foreach ($request->file('phase2_photos', []) as $index => $photo) {
                $photoPath = $photo->store(
                    "bantuan-csr/{$bantuanCsr->id}/photos",
                    'public',
                );
                $newPublicPaths[] = $photoPath;
                $photos[] = [
                    'file_path' => $photoPath,
                    'caption' => $photoCaptions[$index] ?? null,
                ];
            }

            DB::transaction(function () use (
                $request,
                $bantuanCsr,
                $validated,
                $file,
                $path,
                $oldDocuments,
                $photos,
            ) {
                $oldDocuments->each->delete();

                $bantuanCsr->documents()->create([
                    'document_type' => 'bast',
                    'nama_dokumen' => ($validated['bast_document_name'] ?? null)
                        ?: $file->getClientOriginalName(),
                    'file_path' => $path,
                    'uploaded_at' => now(),
                ]);

                $nextPhotoOrder = (int) $bantuanCsr->photos()->max('order') + 1;

                foreach ($photos as $index => $photo) {
                    $bantuanCsr->photos()->create([
                        ...$photo,
                        'order' => $nextPhotoOrder + $index,
                    ]);
                }

                $bantuanCsr->update([
                    'status' => 'pending_fase2',
                    'fase2_reviewed_by' => null,
                    'fase2_reviewed_at' => null,
                    'fase2_rejected_reason' => null,
                ]);

                $this->log(
                    $bantuanCsr,
                    'approved_fase1',
                    'pending_fase2',
                    $request->user()->id,
                    'Dokumen BAST diajukan kepada Super Admin',
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newLocalPaths);
            Storage::disk('public')->delete($newPublicPaths);

            throw $exception;
        }

        $oldDocuments->each(
            fn (BantuanCsrDocument $document) => Storage::disk('local')
                ->delete($document->file_path),
        );

        app(SubmissionNotificationService::class)->assistanceSubmitted(
            $bantuanCsr->fresh(),
            2,
        );

        return redirect()
            ->route('admin.assistance.phase2', $bantuanCsr)
            ->with('success', 'Dokumen BAST berhasil diajukan kepada Super Admin.');
    }

    public function downloadDocument(Request $request, BantuanCsrDocument $document)
    {
        $this->own($request, $document->bantuanCsr);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download(
            $document->file_path,
            $document->nama_dokumen,
        );
    }

    public function destroyDocument(
        Request $request,
        BantuanCsrDocument $document,
    ): RedirectResponse {
        $this->own($request, $document->bantuanCsr);
        $canDeletePhaseOneDocument = $document->bantuanCsr->canBeEdited()
            && array_key_exists(
                $document->document_type,
                BantuanCsr::PHASE_ONE_DOCUMENTS,
            );
        $canDeleteAdditionalBastDocument = $document->bantuanCsr
            ->canUploadBast()
            && $document->document_type
                === BantuanCsr::PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE;

        abort_unless(
            $canDeletePhaseOneDocument || $canDeleteAdditionalBastDocument,
            403,
        );

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'nama_program_bantuan' => ['required', 'string', 'max:255'],
            'deskripsi_bantuan' => ['required', 'string'],
            'pillar_id' => ['required', 'integer', 'exists:pillars,id'],
            'rencana_anggaran' => ['required', 'numeric', 'min:0'],
            'realisasi_anggaran' => ['nullable', 'numeric', 'min:0'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.rincian_kegiatan' => ['required', 'string'],
            'details.*.penerima_bantuan' => ['required', 'string'],
            'details.*.jenis_bantuan' => ['required', 'string'],
            'details.*.quality' => ['required', 'string'],
            'details.*.nominal_bantuan' => ['required', 'numeric', 'min:0'],
            'documents.*.nama' => ['nullable', 'string', 'max:255'],
            'documents.*.file' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
        ]);
    }

    private function phaseOneDocumentErrors(BantuanCsr $item): array
    {
        $existingTypes = $item->documents()
            ->whereIn('document_type', array_keys(BantuanCsr::PHASE_ONE_DOCUMENTS))
            ->pluck('document_type');
        $errors = [];

        foreach (BantuanCsr::PHASE_ONE_DOCUMENTS as $type => $label) {
            if (! $existingTypes->contains($type)) {
                $errors["documents.{$type}.file"] = "Dokumen {$label} wajib diunggah untuk mengajukan Bantuan TJSL.";
            }
        }

        return $errors;
    }

    private function syncChildren(Request $request, BantuanCsr $item): void
    {
        foreach ($request->input('targets', []) as $index => $text) {
            $item->targets()->create([
                'target_text' => $text,
                'order' => $index,
            ]);
        }

        foreach ($request->input('details', []) as $index => $data) {
            $detail = $item->details()->create([
                'rincian_kegiatan' => $data['rincian_kegiatan'],
                'penerima_bantuan' => $data['penerima_bantuan'],
                'jenis_bantuan' => $data['jenis_bantuan'],
                'quality' => $data['quality'],
                'nominal_bantuan' => $data['nominal_bantuan'],
                'order' => $index,
            ]);

        }
    }

    private function replaceChildren(Request $request, BantuanCsr $item): void
    {
        $item->targets()->delete();
        $item->details()->delete();
        $this->syncChildren($request, $item);
    }

    private function syncPhaseOneDocuments(
        Request $request,
        BantuanCsr $item,
    ): void {
        foreach ($request->file('documents', []) as $type => $payload) {
            if (
                ! array_key_exists($type, BantuanCsr::PHASE_ONE_DOCUMENTS)
                || ! isset($payload['file'])
            ) {
                continue;
            }

            $file = $payload['file'];
            $path = $file->store("bantuan-csr/{$item->id}/documents", 'local');
            $oldDocuments = $item->documents()
                ->where('document_type', $type)
                ->get();

            $oldDocuments->each->delete();
            $item->documents()->create([
                'document_type' => $type,
                'nama_dokumen' => $request->input("documents.{$type}.nama")
                    ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'uploaded_at' => now(),
            ]);

            $oldDocuments->each(
                fn (BantuanCsrDocument $document) => Storage::disk('local')
                    ->delete($document->file_path),
            );
        }
    }

    private function log(
        BantuanCsr $item,
        ?string $from,
        string $to,
        int $userId,
        ?string $note = null,
    ): void {
        StatusLog::create([
            'related_type' => 'bantuan_csr',
            'related_id' => $item->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }

    private function own(Request $request, BantuanCsr $item): void
    {
        abort_unless($item->created_by === $request->user()->id, 403);
    }
}
