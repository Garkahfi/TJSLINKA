<?php

namespace App\Services\AdminTjsl;

use App\Models\Pillar;
use App\Models\Program;
use App\Support\TjslSubmissionStatusFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProgramPages
{
    public function __construct(private readonly ProgramWorkflow $workflow) {}

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

    public function show(Request $request, Program $program): View
    {
        $this->workflow->own($request, $program);

        return view('admin.programs.form', [
            'program' => $program->load('pillar', 'documents', 'photos', 'tujuan'),
            'pillars' => Pillar::all(),
            'editable' => $program->canBeEdited(),
        ]);
    }

    public function phaseTwo(Request $request, Program $program): View
    {
        $this->workflow->own($request, $program);
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
}
