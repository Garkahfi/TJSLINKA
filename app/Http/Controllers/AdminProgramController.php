<?php

namespace App\Http\Controllers;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\ProgramDocument;
use App\Models\StatusLog;
use App\Services\SubmissionNotificationService;
use App\Support\TjslSubmissionStatusFilter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminProgramController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = TjslSubmissionStatusFilter::selected($request, true);
        $selectedPillar = $request->filled('pillar')
            ? Pillar::where('slug', $request->query('pillar'))->first()
            : null;

        $programsQuery = Program::with('pillar')
            ->where('created_by', $request->user()->id)
            ->where('is_archived', false)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id));
        $programs = TjslSubmissionStatusFilter::apply($programsQuery, $statusFilter, true)->latest()->get();

        return view('admin.programs.index', [
            'programs' => $programs,
            'selectedPillar' => $selectedPillar,
            'statusFilter' => $statusFilter,
            'statusOptions' => TjslSubmissionStatusFilter::options(true),
        ]);
    }

    public function create(): View
    {
        return view('admin.programs.cooperation-choice');
    }

    public function createCooperationForm(string $jenisKerjasama): View
    {
        $jenisKerjasama = str_replace('-', '_', $jenisKerjasama);
        abort_unless(in_array($jenisKerjasama, Program::COOPERATION_TYPES, true), 404);

        $program = new Program;
        $program->fill(['jenis_kerjasama' => $jenisKerjasama]);

        return view('admin.programs.form', [
            'program' => $program,
            'pillars' => Pillar::all(),
            'editable' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $submit = $request->input('action') === 'submit';

        $program = DB::transaction(function () use ($request, $data, $submit) {
            $program = Program::create([
                ...$data,
                'slug' => $this->slug($data['nama_program']),
                'created_by' => $request->user()->id,
                'status' => 'draft',
                'submitted_at' => null,
            ]);

            $this->syncFiles($request, $program);
            if ($submit && $this->phaseOneDocumentErrors($program) === []) {
                $program->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            $this->log($program, null, $program->status, $request->user()->id);

            return $program;
        });

        $documentErrors = $submit ? $this->phaseOneDocumentErrors($program) : [];

        if ($documentErrors !== []) {
            return redirect()
                ->route('admin.programs.show', $program)
                ->withErrors($documentErrors)
                ->with('success', 'Dokumen yang valid sudah disimpan sebagai draft. Lengkapi dokumen yang masih kurang.');
        }

        if ($program->status === 'pending_fase1') {
            app(SubmissionNotificationService::class)->programSubmitted($program, 1);
        }

        return redirect()
            ->route('admin.programs.show', $program)
            ->with('success', 'Program berhasil disimpan.');
    }

    public function show(Request $request, Program $program): View
    {
        $this->own($request, $program);

        return view('admin.programs.form', [
            'program' => $program->load('pillar', 'documents', 'photos', 'tujuan'),
            'pillars' => Pillar::all(),
            'editable' => $program->canBeEdited(),
        ]);
    }

    public function phaseTwo(Request $request, Program $program): View
    {
        $this->own($request, $program);
        abort_unless(
            ! $program->is_archived
            && in_array($program->status, ['approved_fase1', 'pending_fase2', 'completed'], true),
            403,
        );

        return view('admin.programs.phase-two', [
            'program' => $program->load('documents', 'photos'),
            'bastDocument' => $program->documents->firstWhere('document_type', 'bast'),
            'editable' => $program->canUploadBast(),
        ]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $this->own($request, $program);
        abort_unless($program->canBeEdited(), 403);

        $data = $this->validateData($request);
        $data['jenis_kerjasama'] = $program->jenis_kerjasama ?: $data['jenis_kerjasama'];
        $submit = $request->input('action') === 'submit';

        DB::transaction(function () use ($request, $program, $data, $submit) {
            $from = $program->status;

            $program->update([
                ...$data,
                'slug' => $this->slug($data['nama_program'], $program->id),
                'status' => 'draft',
                'submitted_at' => null,
                'fase1_rejected_reason' => null,
            ]);

            $this->syncFiles($request, $program);
            if ($submit && $this->phaseOneDocumentErrors($program) === []) {
                $program->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            if ($from !== $program->status) {
                $this->log($program, $from, $program->status, $request->user()->id);
            }
        });

        $documentErrors = $submit ? $this->phaseOneDocumentErrors($program) : [];

        if ($documentErrors !== []) {
            return back()
                ->withErrors($documentErrors)
                ->with('success', 'Dokumen yang valid sudah disimpan. Lengkapi dokumen yang masih kurang sebelum mengajukan program.');
        }

        if ($program->status === 'pending_fase1') {
            app(SubmissionNotificationService::class)->programSubmitted($program, 1);
        }

        return back()->with('success', 'Perubahan program tersimpan.');
    }

    public function cancel(Request $request, Program $program): RedirectResponse
    {
        $this->own($request, $program);
        abort_unless($program->status === 'pending_fase1', 403);

        $program->update([
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        $this->log(
            $program,
            'pending_fase1',
            'draft',
            $request->user()->id,
            'Pengajuan dibatalkan Admin',
        );

        return redirect()->route('admin.programs.show', $program);
    }

    public function uploadBast(Request $request, Program $program): RedirectResponse
    {
        $this->own($request, $program);
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

                $this->log(
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
        $this->own($request, $document->program);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_dokumen);
    }

    public function destroyDocument(Request $request, ProgramDocument $document): RedirectResponse
    {
        $this->own($request, $document->program);
        abort_unless($document->program->canBeEdited(), 403);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    private function validateData(Request $request): array
    {
        $request->mergeIfMissing(['jenis_kerjasama' => 'pks']);

        return $request->validate([
            'pillar_id' => ['required', 'exists:pillars,id'],
            'jenis_kerjasama' => ['required', Rule::in(Program::COOPERATION_TYPES)],
            'nama_program' => ['required', 'string', 'max:255'],
            'deskripsi_program' => ['required', 'string'],
            'sasaran_program' => ['required', 'string'],
            'lokasi_program' => ['required', 'string'],
            'mitra_program' => ['required', 'string'],
            'rencana_anggaran' => ['required', 'numeric', 'min:0'],
            'realisasi_anggaran' => ['nullable', 'numeric', 'min:0'],
            'tujuan_program' => ['required', 'string', 'max:5000'],
            'documents.*.nama' => ['nullable', 'string', 'max:255'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
        ]);
    }

    private function phaseOneDocumentErrors(Program $program): array
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

    private function syncFiles(Request $request, Program $program): void
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

    private function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $index = 2;

        while (Program::where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-".$index++;
        }

        return $slug;
    }

    private function own(Request $request, Program $program): void
    {
        abort_unless($program->created_by === $request->user()->id, 403);
    }

    private function log(
        Program $program,
        ?string $from,
        string $to,
        int $userId,
        ?string $note = null,
    ): void {
        StatusLog::create([
            'related_type' => 'program',
            'related_id' => $program->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }
}
