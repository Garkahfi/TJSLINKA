<?php

namespace App\Services\PublicPages;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use App\Services\ProgramMonitoringService;

class PublicProgramOverview
{
    public function __construct(private readonly ProgramMonitoringService $programMonitoring) {}

    public function publicOverviewPrograms(string $keyword = ''): array
    {
        $internalPrograms = Program::with([
            'pillar',
            'documents',
            'creator',
            'fase1Reviewer',
            'fase2Reviewer',
        ])
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program', 'like', "%{$keyword}%"),
            )
            ->latest()
            ->get()
            ->map(function (Program $program): array {
                $detailUrl = ! $program->is_archived
                    && in_array($program->status, Program::PUBLIC_STATUSES, true)
                        ? route('program.detail', $program->slug)
                        : null;

                return $this->programMonitoring->internal($program, $detailUrl);
            });

        $csrPrograms = BantuanCsr::with([
            'pillar',
            'documents',
            'creator',
            'fase1Reviewer',
            'fase2Reviewer',
        ])
            ->whereIn('status', BantuanCsr::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program_bantuan', 'like', "%{$keyword}%"),
            )
            ->latest()
            ->get()
            ->map(fn (BantuanCsr $program): array => $this->programMonitoring->csr($program));

        return $internalPrograms
            ->concat($csrPrograms)
            ->sortByDesc('sort_timestamp')
            ->values()
            ->all();
    }

    public function monitoringRevision(string $keyword, ?int $year): string
    {
        $internal = Program::query()
            ->whereIn('status', Program::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program', 'like', "%{$keyword}%"),
            )
            ->select([
                'id',
                'slug',
                'pillar_id',
                'jenis_kerjasama',
                'nama_program',
                'status',
                'is_archived',
                'fase1_reviewed_at',
                'fase2_reviewed_at',
                'updated_at',
            ])
            ->withCount([
                'documents as document_a_count' => fn ($query) => $query
                    ->whereIn('document_type', [
                        'A',
                        'proposal_pengajuan_program',
                        'surat_penawaran_balasan',
                    ]),
                'documents as document_b_count' => fn ($query) => $query
                    ->whereIn('document_type', [
                        'B',
                        'kelengkapan_survei',
                        'bukti_penjajakan',
                    ]),
                'documents as document_c_count' => fn ($query) => $query
                    ->whereIn('document_type', ['C', 'kajian_kelayakan']),
                'documents as document_d_count' => fn ($query) => $query
                    ->whereIn('document_type', ['D', 'kajian_mitigasi_risiko']),
                'documents as document_e_count' => fn ($query) => $query
                    ->whereIn('document_type', ['E', 'perjanjian_kerja_sama']),
                'documents as document_f_count' => fn ($query) => $query
                    ->whereIn('document_type', ['F', 'bast']),
            ])
            ->orderBy('id')
            ->get()
            ->toArray();

        $csr = BantuanCsr::query()
            ->whereIn('status', BantuanCsr::OVERVIEW_STATUSES)
            ->when(
                $keyword !== '',
                fn ($query) => $query->where('nama_program_bantuan', 'like', "%{$keyword}%"),
            )
            ->select([
                'id',
                'pillar_id',
                'nama_program_bantuan',
                'status',
                'fase1_reviewed_at',
                'fase2_reviewed_at',
                'updated_at',
            ])
            ->withCount([
                'documents as document_a_count' => fn ($query) => $query
                    ->whereIn('document_type', ['A', 'proposal_pengajuan_program']),
                'documents as document_b_count' => fn ($query) => $query
                    ->whereIn('document_type', ['B', 'kelengkapan_survei']),
                'documents as document_f_count' => fn ($query) => $query
                    ->whereIn('document_type', ['F', 'bast']),
            ])
            ->orderBy('id')
            ->get()
            ->toArray();

        $pillars = Pillar::query()
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'color_hex', 'updated_at'])
            ->toArray();

        return hash('sha256', json_encode([
            'keyword' => $keyword,
            'year' => $year,
            'internal' => $internal,
            'csr' => $csr,
            'pillars' => $pillars,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
