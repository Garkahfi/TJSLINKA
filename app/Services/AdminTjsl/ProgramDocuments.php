<?php

namespace App\Services\AdminTjsl;

use App\Models\Program;
use App\Models\ProgramDocument;
use App\Services\SubmissionNotificationService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProgramDocuments
{
    public function __construct(private readonly ProgramWorkflow $workflow) {}

    public function uploadBast(Request $request, Program $program): RedirectResponse
    {
        $this->workflow->own($request, $program);
        abort_unless($program->canUploadBast(), 403);

        $validated = $request->validate([
            'dokumen_f' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'bast_document_name' => ['nullable', 'string', 'max:255'],
            'phase2_photos' => ['nullable', 'array', 'max:10'],
            'phase2_photos.*' => ['image', 'max:20480'],
            'phase2_photo_captions' => ['nullable', 'array', 'max:10'],
            'phase2_photo_captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        $uploadedFile = $validated['dokumen_f'];
        $newPath = $uploadedFile->store("programs/{$program->id}/documents", 'local');
        $oldDocuments = $program->documents()->where('document_type', 'bast')->get();
        $newPhotos = collect();
        $photoCaptions = $validated['phase2_photo_captions'] ?? [];

        try {
            foreach ($request->file('phase2_photos', []) as $index => $photo) {
                $newPhotos->push([
                    'path' => $photo->store("programs/{$program->id}/photos", 'public'),
                    'caption' => $photoCaptions[$index] ?? null,
                ]);
            }

            DB::transaction(function () use (
                $request,
                $program,
                $uploadedFile,
                $validated,
                $newPath,
                $oldDocuments,
                $newPhotos,
            ) {
                $oldDocuments->each->delete();

                $program->documents()->create([
                    'document_type' => 'bast',
                    'nama_dokumen' => ($validated['bast_document_name'] ?? null)
                        ?: $uploadedFile->getClientOriginalName(),
                    'file_path' => $newPath,
                    'uploaded_at' => now(),
                ]);

                $nextPhotoOrder = (int) ($program->photos()->max('order') ?? 0);
                $hasExistingPhotos = $program->photos()->exists();

                $newPhotos->each(function (array $photo, int $index) use (
                    $program,
                    $nextPhotoOrder,
                    $hasExistingPhotos,
                ): void {
                    $program->photos()->create([
                        'file_path' => $photo['path'],
                        'caption' => $photo['caption'],
                        'is_cover' => ! $hasExistingPhotos && $index === 0,
                        'order' => $nextPhotoOrder + $index + 1,
                    ]);
                });

                $program->update([
                    'status' => 'pending_fase2',
                    'fase2_reviewed_by' => null,
                    'fase2_reviewed_at' => null,
                    'fase2_rejected_reason' => null,
                ]);

                $this->workflow->log(
                    $program,
                    'approved_fase1',
                    'pending_fase2',
                    $request->user()->id,
                    'Dokumen BAST diajukan untuk review Fase 2',
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            $newPhotos->each(
                fn (array $photo) => Storage::disk('public')->delete($photo['path']),
            );
            throw $exception;
        }

        $oldDocuments->each(fn (ProgramDocument $document) => Storage::disk('local')->delete($document->file_path));
        app(SubmissionNotificationService::class)->programSubmitted($program->fresh(), 2);

        return redirect()
            ->route('admin.programs.phase2', $program)
            ->with('success', 'Dokumen BAST dan dokumentasi tambahan berhasil diajukan kepada Super Admin.');
    }

    public function downloadDocument(Request $request, ProgramDocument $document)
    {
        $this->workflow->own($request, $document->program);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_dokumen);
    }

    public function destroyDocument(Request $request, ProgramDocument $document): RedirectResponse
    {
        $this->workflow->own($request, $document->program);
        abort_unless($document->program->canBeEdited(), 403);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    public function phaseOneDocumentErrors(Program $program): array
    {
        $requiredDocuments = $program->phaseOneDocuments();
        $existingTypes = $program->documents()
            ->whereIn('document_type', array_keys($requiredDocuments))
            ->pluck('document_type');
        $errors = [];

        foreach ($requiredDocuments as $type => $label) {
            if (! $existingTypes->contains($type)) {
                $errors["documents.{$type}.file"] = "Dokumen {$label} wajib diunggah untuk mengajukan program.";
            }
        }

        return $errors;
    }

    public function syncFiles(Request $request, Program $program): void
    {
        $applicableDocuments = $program->phaseOneDocuments();

        foreach ($request->file('documents', []) as $type => $payload) {
            if (! isset($payload['file'])) {
                continue;
            }

            if (
                array_key_exists($type, Program::PHASE_ONE_DOCUMENTS)
                && ! array_key_exists($type, $applicableDocuments)
            ) {
                continue;
            }

            $documentType = array_key_exists($type, $applicableDocuments)
                ? $type
                : 'lainnya';

            if ($documentType !== 'lainnya') {
                $existingDocuments = $program->documents()->where('document_type', $documentType)->get();
                $existingDocuments->each(fn (ProgramDocument $document) => Storage::disk('local')->delete($document->file_path));
                $existingDocuments->each->delete();
            }

            $program->documents()->create([
                'document_type' => $documentType,
                'nama_dokumen' => $request->input("documents.{$type}.nama")
                    ?: $payload['file']->getClientOriginalName(),
                'file_path' => $payload['file']->store("programs/{$program->id}/documents", 'local'),
                'uploaded_at' => now(),
            ]);
        }

    }
}
