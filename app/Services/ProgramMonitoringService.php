<?php

namespace App\Services;

use App\Models\BantuanCsr;
use App\Models\Program;
use App\Models\User;

class ProgramMonitoringService
{
    public function internal(Program $program, ?string $detailUrl = null): array
    {
        $program->loadMissing([
            'pillar',
            'documents',
            'creator',
            'fase1Reviewer',
            'fase2Reviewer',
        ]);

        $isInitialRejection = $program->status === 'rejected_fase1';
        $reviewer = $program->fase2Reviewer ?: $program->fase1Reviewer;
        $reviewedAt = $program->fase2_reviewed_at ?: $program->fase1_reviewed_at;
        $rejectedReason = $isInitialRejection
            ? $program->fase1_rejected_reason
            : $program->fase2_rejected_reason;
        $documentTypes = $program->documents
            ->pluck('document_type')
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $program->id,
            'jenis' => 'internal',
            'jenis_kerjasama' => $program->jenis_kerjasama ?: 'pks',
            'slug' => $program->slug,
            'pillar' => $program->pillar?->slug ?: 'tanpa-pilar',
            'pillar_label' => $program->pillar?->name ?: 'Pilar Belum Ditentukan',
            'pillar_color' => $program->pillar?->color_hex ?: '#64748b',
            'title' => $program->nama_program,
            'status' => $program->status,
            'document_types' => $documentTypes,
            'document_codes' => $this->internalDocumentCodes($documentTypes),
            'detail_url' => $detailUrl,
            'creator_name' => $this->userName($program->creator),
            'rejected_reason' => $rejectedReason,
            'reviewer_name' => $this->userName($reviewer),
            'reviewed_at' => $reviewedAt?->format('d/m/Y H.i'),
            'monitoring_year' => (int) ($reviewedAt?->format('Y') ?? $program->updated_at?->format('Y') ?? now()->format('Y')),
            'sort_timestamp' => $program->updated_at?->timestamp ?? 0,
        ];
    }

    public function csr(BantuanCsr $program, ?string $detailUrl = null): array
    {
        $program->loadMissing([
            'pillar',
            'documents',
            'creator',
            'fase1Reviewer',
            'fase2Reviewer',
        ]);

        $reviewer = $program->fase2Reviewer ?: $program->fase1Reviewer;
        $reviewedAt = $program->fase2_reviewed_at ?: $program->fase1_reviewed_at;
        $documentTypes = $program->documents
            ->pluck('document_type')
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $program->id,
            'jenis' => 'csr',
            'jenis_kerjasama' => null,
            'slug' => null,
            'pillar' => $program->pillar?->slug ?: 'tanpa-pilar',
            'pillar_label' => $program->pillar?->name ?: 'Pilar Belum Ditentukan',
            'pillar_color' => $program->pillar?->color_hex ?: '#64748b',
            'title' => $program->nama_program_bantuan,
            'status' => $program->status,
            'document_types' => $documentTypes,
            'document_codes' => collect($documentTypes)
                ->map(fn (string $type): ?string => match ($type) {
                    'A', 'proposal_pengajuan_program' => 'A',
                    'B', 'kelengkapan_survei' => 'B',
                    'F', 'bast' => 'F',
                    default => null,
                })
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'detail_url' => $detailUrl,
            'creator_name' => $this->userName($program->creator),
            'rejected_reason' => $program->fase2_rejected_reason ?: $program->fase1_rejected_reason,
            'reviewer_name' => $this->userName($reviewer),
            'reviewed_at' => $reviewedAt?->format('d/m/Y H.i'),
            'monitoring_year' => (int) ($reviewedAt?->format('Y') ?? $program->updated_at?->format('Y') ?? now()->format('Y')),
            'sort_timestamp' => $program->updated_at?->timestamp ?? 0,
        ];
    }

    /**
     * Normalise nama document_type lama dan baru ke kontrak checklist A-F.
     *
     * @param  array<int, string>  $documentTypes
     * @return array<int, string>
     */
    private function internalDocumentCodes(array $documentTypes): array
    {
        return collect($documentTypes)
            ->map(fn (string $type): ?string => match ($type) {
                'A', 'proposal_pengajuan_program', 'surat_penawaran_balasan' => 'A',
                'B', 'kelengkapan_survei', 'bukti_penjajakan' => 'B',
                'C', 'kajian_kelayakan' => 'C',
                'D', 'kajian_mitigasi_risiko' => 'D',
                'E', 'perjanjian_kerja_sama' => 'E',
                'F', 'bast' => 'F',
                default => null,
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function userName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $profileName = trim(implode(' ', array_filter([
            $user->nama_depan,
            $user->nama_belakang,
        ])));

        return $profileName ?: ($user->name ?: $user->username);
    }
}
