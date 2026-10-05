<?php

namespace App\Services\AdminTjsl;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Support\TjslSubmissionStatusFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BantuanCsrPages
{
    public function __construct(private readonly BantuanCsrWorkflow $workflow) {}

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

    public function show(Request $request, BantuanCsr $bantuanCsr): View
    {
        $this->workflow->own($request, $bantuanCsr);

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
        $this->workflow->own($request, $bantuanCsr);
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
}
