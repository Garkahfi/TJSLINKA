<?php

namespace App\Services\AdminTjsl;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Services\SubmissionNotificationService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BantuanCsrDocuments
{
    public function __construct(private readonly BantuanCsrWorkflow $workflow) {}

    public function uploadBast(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        $this->workflow->own($request, $bantuanCsr);
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

                $this->workflow->log(
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
        $this->workflow->own($request, $document->bantuanCsr);

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
        $this->workflow->own($request, $document->bantuanCsr);
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

    public function phaseOneDocumentErrors(BantuanCsr $item): array
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

    public function syncPhaseOneDocuments(
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
}
