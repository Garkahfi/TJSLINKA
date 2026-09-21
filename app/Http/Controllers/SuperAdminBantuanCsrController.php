<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Models\Pillar;
use App\Models\StatusLog;
use App\Support\TjslSubmissionStatusFilter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SuperAdminBantuanCsrController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = TjslSubmissionStatusFilter::selected($request, false);

        return view('superadmin.assistance.index', [
            'bantuanPerPilar' => Pillar::query()
                ->orderBy('id')
                ->with(['bantuanCsr' => fn ($query) => $query
                    ->with('creator')
                    ->where('is_archived', false)
                    ->tap(fn ($query) => TjslSubmissionStatusFilter::apply($query, $statusFilter, false))
                    ->latest()])
                ->get(),
            'bantuanTanpaPilar' => BantuanCsr::with('creator')
                ->where('is_archived', false)
                ->tap(fn ($query) => TjslSubmissionStatusFilter::apply($query, $statusFilter, false))
                ->whereNull('pillar_id')
                ->latest()
                ->get(),
            'statusFilter' => $statusFilter,
            'statusOptions' => TjslSubmissionStatusFilter::options(false),
        ]);
    }

    public function show(BantuanCsr $bantuanCsr): View
    {
        return view('admin.assistance.form', [
            'item' => $bantuanCsr->load(
                'documents',
                'targets',
                'details.photos',
                'photos',
                'creator',
            ),
            'pillars' => Pillar::orderBy('id')->get(),
            'editable' => false,
            'routePrefix' => 'superadmin',
            'isSuperAdmin' => true,
        ]);
    }

    public function approve(
        Request $request,
        BantuanCsr $bantuanCsr,
    ): RedirectResponse {
        abort_if($bantuanCsr->is_archived, 422);
        abort_unless(
            in_array($bantuanCsr->status, BantuanCsr::REVIEW_STATUSES, true),
            422,
        );

        $from = $bantuanCsr->status;

        if ($from === 'pending_fase1') {
            $this->ensurePhaseOneDocuments($bantuanCsr);

            $bantuanCsr->update([
                'status' => 'approved_fase1',
                'fase1_reviewed_by' => $request->user()->id,
                'fase1_reviewed_at' => now(),
                'fase1_rejected_reason' => null,
            ]);

            $title = 'Pengajuan Bantuan TJSL Disetujui';
            $message = 'Bantuan "'.$bantuanCsr->nama_program_bantuan
                .'" disetujui. Admin dapat mengunggah dokumen BAST.';
            $success = 'Dokumen awal Bantuan TJSL disetujui.';
        } else {
            abort_unless(
                $bantuanCsr->documents()
                    ->where('document_type', 'bast')
                    ->exists(),
                422,
            );

            $bantuanCsr->update([
                'status' => 'completed',
                'fase2_reviewed_by' => $request->user()->id,
                'fase2_reviewed_at' => now(),
                'fase2_rejected_reason' => null,
            ]);

            $title = 'Dokumen BAST Bantuan TJSL Disetujui';
            $message = 'Dokumen BAST bantuan "'
                .$bantuanCsr->nama_program_bantuan
                .'" telah disetujui. Bantuan dinyatakan selesai.';
            $success = 'Dokumen BAST disetujui dan Bantuan TJSL telah selesai.';
        }

        $this->log(
            $bantuanCsr,
            $from,
            $bantuanCsr->status,
            $request->user()->id,
        );
        $this->notifyCreator($bantuanCsr, $title, $message);

        $response = back()->with('success', $success);

        if ($from === 'pending_fase2') {
            $response->with('approval_completed', [
                'type' => 'csr',
                'name' => $bantuanCsr->nama_program_bantuan,
            ]);
        }

        return $response;
    }

    public function reject(
        Request $request,
        BantuanCsr $bantuanCsr,
    ): RedirectResponse {
        $data = $request->validate([
            'rejected_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        abort_if($bantuanCsr->is_archived, 422);
        abort_unless(
            in_array($bantuanCsr->status, BantuanCsr::REVIEW_STATUSES, true),
            422,
        );

        $from = $bantuanCsr->status;

        if ($from === 'pending_fase1') {
            $bantuanCsr->update([
                'status' => 'rejected_fase1',
                'fase1_reviewed_by' => $request->user()->id,
                'fase1_reviewed_at' => now(),
                'fase1_rejected_reason' => $data['rejected_reason'],
            ]);

            $title = 'Pengajuan Bantuan TJSL Ditolak';
            $message = 'Bantuan "'.$bantuanCsr->nama_program_bantuan
                .'" ditolak: '.$data['rejected_reason']
                .'. Pengajuan ini berakhir dan tidak dapat dilanjutkan ke BAST.';
            $success = 'Pengajuan Bantuan TJSL ditolak dan alurnya telah berakhir.';
        } else {
            $bantuanCsr->update([
                'status' => 'approved_fase1',
                'fase2_reviewed_by' => $request->user()->id,
                'fase2_reviewed_at' => now(),
                'fase2_rejected_reason' => $data['rejected_reason'],
            ]);

            $title = 'Dokumen BAST Bantuan TJSL Ditolak';
            $message = 'Dokumen BAST bantuan "'
                .$bantuanCsr->nama_program_bantuan
                .'" ditolak: '.$data['rejected_reason']
                .'. Silakan unggah ulang BAST.';
            $success = 'Dokumen BAST ditolak. Admin dapat mengunggah ulang tanpa mengulang dokumen awal.';
        }

        $this->log(
            $bantuanCsr,
            $from,
            $bantuanCsr->status,
            $request->user()->id,
            $data['rejected_reason'],
        );
        $this->notifyCreator($bantuanCsr, $title, $message);

        return back()->with('success', $success);
    }

    public function downloadDocument(BantuanCsrDocument $document)
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download(
            $document->file_path,
            $document->nama_dokumen,
        );
    }

    private function ensurePhaseOneDocuments(BantuanCsr $item): void
    {
        $types = $item->documents()
            ->whereIn('document_type', array_keys(BantuanCsr::PHASE_ONE_DOCUMENTS))
            ->pluck('document_type');

        abort_unless(
            collect(array_keys(BantuanCsr::PHASE_ONE_DOCUMENTS))
                ->every(fn (string $type) => $types->contains($type)),
            422,
            'Dokumen awal Bantuan TJSL belum lengkap.',
        );
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

    private function notifyCreator(
        BantuanCsr $item,
        string $title,
        string $message,
    ): void {
        if (! $item->created_by) {
            return;
        }

        AdminNotification::create([
            'user_id' => $item->created_by,
            'title' => $title,
            'message' => $message,
            'related_type' => 'bantuan_csr',
            'related_id' => $item->id,
        ]);
    }
}
