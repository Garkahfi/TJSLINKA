<?php

namespace App\Services\AdminTjsl;

use App\Models\BantuanCsr;
use App\Services\SubmissionNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BantuanCsrChanges
{
    public function __construct(
        private readonly BantuanCsrDocuments $documents,
        private readonly BantuanCsrWorkflow $workflow,
    ) {}

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
            $this->documents->syncPhaseOneDocuments($request, $item);

            if ($submit && $this->documents->phaseOneDocumentErrors($item) === []) {
                $item->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            $this->workflow->log($item, null, $item->status, $request->user()->id);

            return $item;
        });

        $documentErrors = $submit ? $this->documents->phaseOneDocumentErrors($item) : [];

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

    public function update(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        $this->workflow->own($request, $bantuanCsr);
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
            $this->documents->syncPhaseOneDocuments($request, $bantuanCsr);

            if ($submit && $this->documents->phaseOneDocumentErrors($bantuanCsr) === []) {
                $bantuanCsr->update([
                    'status' => 'pending_fase1',
                    'submitted_at' => now(),
                ]);
            }

            if ($from !== $bantuanCsr->status) {
                $this->workflow->log(
                    $bantuanCsr,
                    $from,
                    $bantuanCsr->status,
                    $request->user()->id,
                );
            }
        });

        $documentErrors = $submit ? $this->documents->phaseOneDocumentErrors($bantuanCsr) : [];

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
        $this->workflow->own($request, $bantuanCsr);
        abort_unless($bantuanCsr->status === 'pending_fase1', 403);

        $bantuanCsr->update([
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        $this->workflow->log(
            $bantuanCsr,
            'pending_fase1',
            'draft',
            $request->user()->id,
            'Pengajuan dibatalkan Admin',
        );

        return redirect()->route('admin.assistance.show', $bantuanCsr);
    }

    public function data(Request $request): array
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

    public function syncChildren(Request $request, BantuanCsr $item): void
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

    public function replaceChildren(Request $request, BantuanCsr $item): void
    {
        $item->targets()->delete();
        $item->details()->delete();
        $this->syncChildren($request, $item);
    }
}
