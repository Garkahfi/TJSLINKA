<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\ProgramDocument;
use App\Models\StatusLog;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuperAdminProgramController extends Controller
{
    public function index(Request $request): View
    {
        $selectedPillar = $request->filled('pillar')
            ? Pillar::where('slug', $request->query('pillar'))->first()
            : null;

        $programs = Program::with('pillar', 'creator')
            ->where('is_archived', false)
            ->whereIn('status', Program::PUBLIC_STATUSES)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id))
            ->latest()
            ->get();

        return view('superadmin.programs.index', compact('programs', 'selectedPillar'));
    }

    public function show(Program $program): View
    {
        return view('admin.programs.form', [
            'program' => $program->load('pillar', 'documents', 'photos', 'tujuan', 'creator'),
            'pillars' => Pillar::all(),
            'editable' => false,
            'routePrefix' => 'superadmin',
            'isSuperAdmin' => true,
        ]);
    }

    public function approve(Request $request, Program $program): RedirectResponse
    {
        abort_unless(in_array($program->status, ['pending_fase1', 'pending_fase2'], true), 422);

        $from = $program->status;

        if ($from === 'pending_fase1') {
            $this->ensurePhaseOneDocuments($program);

            $program->update([
                'status' => 'approved_fase1',
                'fase1_reviewed_by' => $request->user()->id,
                'fase1_reviewed_at' => now(),
                'fase1_rejected_reason' => null,
            ]);

            $message = 'Program "'.$program->nama_program.'" telah disetujui. Admin dapat mengunggah dokumen BAST.';
            $success = 'Program disetujui.';
        } else {
            abort_unless($program->documents()->where('document_type', 'bast')->exists(), 422);

            $program->update([
                'status' => 'completed',
                'fase2_reviewed_by' => $request->user()->id,
                'fase2_reviewed_at' => now(),
                'fase2_rejected_reason' => null,
            ]);

            $message = 'Dokumen BAST program "'.$program->nama_program.'" telah disetujui. Program dinyatakan selesai.';
            $success = 'Fase 2 disetujui dan program telah selesai.';
        }

        $this->log($program, $from, $program->status, $request->user()->id);
        $this->notifyCreator($program, 'Review Program TJSL Diterima', $message);

        $response = back()->with('success', $success);

        if ($from === 'pending_fase2') {
            $response->with('approval_completed', [
                'type' => 'internal',
                'name' => $program->nama_program,
            ]);
        }

        return $response;
    }

    public function reject(Request $request, Program $program): RedirectResponse
    {
        $data = $request->validate([
            'rejected_reason' => ['required', 'string', 'min:5'],
        ]);

        abort_unless(in_array($program->status, ['pending_fase1', 'pending_fase2'], true), 422);

        $from = $program->status;

        if ($from === 'pending_fase1') {
            $program->update([
                'status' => 'rejected_fase1',
                'fase1_reviewed_by' => $request->user()->id,
                'fase1_reviewed_at' => now(),
                'fase1_rejected_reason' => $data['rejected_reason'],
            ]);

            $title = 'Pengajuan Program TJSL Ditolak';
            $message = 'Program "'.$program->nama_program.'" ditolak: '.$data['rejected_reason'];
            $success = 'Program ditolak dan tetap tercatat pada Overview Program TJSL.';
        } else {
            $program->update([
                'status' => 'approved_fase1',
                'fase2_reviewed_by' => $request->user()->id,
                'fase2_reviewed_at' => now(),
                'fase2_rejected_reason' => $data['rejected_reason'],
            ]);

            $title = 'Dokumen BAST Program TJSL Ditolak';
            $message = 'Dokumen BAST program "'.$program->nama_program.'" ditolak: '.$data['rejected_reason'].'. Silakan unggah ulang BAST.';
            $success = 'Dokumen BAST ditolak. Admin dapat mengunggah ulang tanpa mengulang dokumen awal.';
        }

        $this->log($program, $from, $program->status, $request->user()->id, $data['rejected_reason']);
        $this->notifyCreator($program, $title, $message);

        return back()->with('success', $success);
    }

    public function downloadDocument(ProgramDocument $document)
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_dokumen);
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

    private function notifyCreator(Program $program, string $title, string $message): void
    {
        if (! $program->created_by) {
            return;
        }

        AdminNotification::create([
            'user_id' => $program->created_by,
            'title' => $title,
            'message' => $message,
            'related_type' => 'program',
            'related_id' => $program->id,
        ]);
    }

    private function ensurePhaseOneDocuments(Program $program): void
    {
        $requiredDocuments = $program->phaseOneDocuments();
        $existingTypes = $program->documents()
            ->whereIn('document_type', array_keys($requiredDocuments))
            ->pluck('document_type');

        $missingDocuments = collect($requiredDocuments)
            ->reject(fn ($label, $type) => $existingTypes->contains($type))
            ->values();

        if ($missingDocuments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'phase_one_documents' => 'Program belum dapat disetujui. Dokumen yang belum lengkap: '
                    .$missingDocuments->join(', ').'.',
            ]);
        }
    }
}
