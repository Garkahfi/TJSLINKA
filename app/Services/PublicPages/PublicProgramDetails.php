<?php

namespace App\Services\PublicPages;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Models\Pillar;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicProgramDetails
{
    public function rincian(Request $request): View
    {
        $pillars = Pillar::query()->orderBy('id')->get();
        $selectedPillar = $request->filled('pilar')
            ? $pillars->firstWhere('slug', $request->query('pilar'))
            : null;

        abort_if($request->filled('pilar') && ! $selectedPillar, 404);

        $internalQuery = Program::query()
            ->selectRaw("id, 'internal' as jenis, pillar_id, updated_at")
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->where('is_archived', false)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id));

        $csrQuery = BantuanCsr::query()
            ->selectRaw("id, 'csr' as jenis, pillar_id, updated_at")
            ->whereIn('status', BantuanCsr::OVERVIEW_STATUSES)
            ->where('is_archived', false)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id));

        $programs = DB::query()
            ->fromSub($internalQuery->unionAll($csrQuery), 'rincian_programs')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();

        $references = $programs->getCollection();
        $internalPrograms = Program::with('pillar', 'documents', 'photos', 'tujuan')
            ->whereIn('id', $references->where('jenis', 'internal')->pluck('id'))
            ->get()
            ->keyBy('id');
        $csrPrograms = BantuanCsr::with('pillar', 'photos', 'details.photos')
            ->whereIn('id', $references->where('jenis', 'csr')->pluck('id'))
            ->get()
            ->keyBy('id');

        $programs->setCollection($references->map(function (object $reference) use ($internalPrograms, $csrPrograms): array {
            if ($reference->jenis === 'csr') {
                return $this->csrCardArray($csrPrograms->get($reference->id));
            }

            return $this->programArray($internalPrograms->get($reference->id));
        }));

        return view('pages.program-rincian', compact('programs', 'pillars', 'selectedPillar'));
    }

    public function detail(string $slug): View
    {
        /** @var Program $model */
        $model = Program::with('pillar', 'documents', 'photos', 'tujuan')
            ->where('slug', $slug)
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->where('is_archived', false)
            ->firstOrFail();
        $program = $this->programArray($model);

        return view('pages.program-detail', compact('program'));
    }

    public function csrDetail(BantuanCsr $bantuanCsr): View
    {
        abort_unless(
            in_array($bantuanCsr->status, BantuanCsr::OVERVIEW_STATUSES, true)
                && ! $bantuanCsr->is_archived,
            404,
        );

        $bantuanCsr->load([
            'pillar',
            'targets',
            'documents',
            'photos',
            'details.photos',
        ]);

        return view('pages.csr-detail', [
            'program' => $this->csrDetailArray($bantuanCsr),
        ]);
    }

    private function programArray(Program $program): array
    {
        $photos = $program->photos->map(fn ($photo) => Storage::url($photo->file_path))->all();
        $legacyGoal = trim((string) $program->tujuan_program);
        $goals = $legacyGoal !== ''
            ? [[
                'description' => $legacyGoal,
                'image' => null,
            ]]
            : $program->tujuan->map(fn ($tujuan) => [
                'description' => $tujuan->deskripsi,
                'image' => $tujuan->foto_path ? Storage::url($tujuan->foto_path) : null,
            ])->all();

        return [
            'id' => $program->id,
            'jenis' => 'internal',
            'jenis_kerjasama' => $program->jenis_kerjasama ?: 'pks',
            'slug' => $program->slug,
            'pillar' => $program->pillar->slug,
            'title' => $program->nama_program,
            'status' => $program->status,
            'detail_url' => route('program.detail', $program->slug),
            'cover_image' => $photos[0] ?? 'images/programs/sample-1.png',
            'description' => $program->deskripsi_program,
            'sasaran' => $program->sasaran_program,
            'lokasi' => $program->lokasi_program,
            'mitra' => $program->mitra_program,
            'budget_planned' => (float) $program->rencana_anggaran,
            'budget_realized' => (float) $program->realisasi_anggaran,
            'gallery' => $photos,
            'goals' => $goals,
            'document_types' => $program->documents->pluck('document_type')->unique()->values()->all(),
            'documents' => $program->documents->map(fn ($document) => [
                'name' => $document->nama_dokumen,
                'checked' => true,
                'view_url' => route('program.documents.view', [$program, $document]),
                'download_url' => route('program.documents.download', [$program, $document]),
            ])->all(),
            'sort_timestamp' => $program->updated_at?->timestamp ?? 0,
        ];
    }

    private function csrCardArray(BantuanCsr $program): array
    {
        $coverImage = $program->photos->first()?->file_path
            ?? $program->details->flatMap->photos->first()?->file_path;

        return [
            'id' => $program->id,
            'jenis' => 'csr',
            'slug' => null,
            'pillar' => $program->pillar?->slug ?? 'belum-ditentukan',
            'title' => $program->nama_program_bantuan,
            'status' => $program->status,
            'detail_url' => route('program.csr.detail', $program),
            'cover_image' => $coverImage
                ? Storage::url($coverImage)
                : null,
            'sort_timestamp' => $program->updated_at?->timestamp ?? 0,
        ];
    }

    private function csrDetailArray(BantuanCsr $program): array
    {
        $programPhotos = $program->photos
            ->map(fn ($photo): array => [
                'url' => Storage::url($photo->file_path),
                'caption' => $photo->caption,
            ]);
        $detailPhotos = $program->details
            ->flatMap(fn ($detail) => $detail->photos->map(fn ($photo): array => [
                'url' => Storage::url($photo->file_path),
                'caption' => $photo->caption,
            ]));
        $gallery = $programPhotos
            ->concat($detailPhotos)
            ->unique('url')
            ->values();
        $targetImages = $detailPhotos
            ->concat($programPhotos)
            ->unique('url')
            ->values();

        return [
            'id' => $program->id,
            'title' => $program->nama_program_bantuan,
            'pillar' => $program->pillar?->slug,
            'description' => $program->deskripsi_bantuan,
            'status' => $program->status,
            'budget_planned' => (float) $program->rencana_anggaran,
            'budget_realized' => (float) $program->realisasi_anggaran,
            'cover_image' => $gallery->first()['url'] ?? null,
            'gallery' => $gallery->all(),
            'targets' => $program->targets->values()->map(fn ($target, int $index): array => [
                'description' => $target->target_text,
                'image' => $targetImages->get($index)['url'] ?? null,
            ])->all(),
            'details' => $program->details->values()->map(fn ($detail): array => [
                'activity' => $detail->rincian_kegiatan,
                'recipient' => $detail->penerima_bantuan,
                'assistance_type' => $detail->jenis_bantuan,
                'quantity' => $detail->quality,
                'nominal' => (float) $detail->nominal_bantuan,
            ])->all(),
            'documents' => $program->documents->values()->map(fn (BantuanCsrDocument $document): array => [
                'name' => $document->nama_dokumen,
                'type' => $this->csrDocumentLabel($document->document_type),
                'checked' => true,
                'view_url' => route('program.csr.documents.view', [$program, $document]),
                'download_url' => route('program.csr.documents.download', [$program, $document]),
            ])->all(),
        ];
    }

    private function csrDocumentLabel(string $type): string
    {
        return match ($type) {
            'A', 'proposal_pengajuan_program', 'proposal_permintaan' => 'Proposal Pengajuan Program',
            'B', 'kelengkapan_survei' => 'Kelengkapan Survei',
            'F', 'bast' => 'Berita Acara Serah Terima (BAST)',
            BantuanCsr::PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE => 'Dokumen Tambahan BAST',
            default => 'Dokumen Bantuan TJSL',
        };
    }
}
