<?php

namespace App\Services\AdminTjsl;

use App\Models\Program;
use App\Services\SubmissionNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProgramChanges
{
    public function __construct(
        private readonly ProgramDocuments $documents,
        private readonly ProgramWorkflow $workflow,
    ) {}

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

            $this->documents->syncFiles($request, $program);
            if ($submit && $this->documents->phaseOneDocumentErrors($program) === []) {
                $program->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            $this->workflow->log($program, null, $program->status, $request->user()->id);

            return $program;
        });

        $documentErrors = $submit ? $this->documents->phaseOneDocumentErrors($program) : [];

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

    public function update(Request $request, Program $program): RedirectResponse
    {
        $this->workflow->own($request, $program);
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

            $this->documents->syncFiles($request, $program);
            if ($submit && $this->documents->phaseOneDocumentErrors($program) === []) {
                $program->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            if ($from !== $program->status) {
                $this->workflow->log($program, $from, $program->status, $request->user()->id);
            }
        });

        $documentErrors = $submit ? $this->documents->phaseOneDocumentErrors($program) : [];

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
        $this->workflow->own($request, $program);
        abort_unless($program->status === 'pending_fase1', 403);

        $program->update([
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        $this->workflow->log(
            $program,
            'pending_fase1',
            'draft',
            $request->user()->id,
            'Pengajuan dibatalkan Admin',
        );

        return redirect()->route('admin.programs.show', $program);
    }

    public function validateData(Request $request): array
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

    public function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $index = 2;

        while (Program::where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-".$index++;
        }

        return $slug;
    }
}
