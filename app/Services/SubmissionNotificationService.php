<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\BantuanCsr;
use App\Models\Program;
use App\Models\User;

class SubmissionNotificationService
{
    public function programSubmitted(Program $program, int $phase = 1): void
    {
        $program->loadMissing('creator');

        $isBastSubmission = $phase === 2;

        $this->notifySuperAdmins(
            ($isBastSubmission ? 'Pengajuan Dokumen BAST Program TJSL oleh ' : 'Pengajuan Program TJSL oleh ')
                .($program->creator?->name ?? 'Admin'),
            $isBastSubmission
                ? "Dokumen BAST program \"{$program->nama_program}\" menunggu persetujuan Super Admin."
                : "Program \"{$program->nama_program}\" menunggu persetujuan Super Admin.",
            'program',
            $program->id,
        );
    }

    public function assistanceSubmitted(BantuanCsr $item, int $phase = 1): void
    {
        $item->loadMissing('creator');
        $isBastSubmission = $phase === 2;

        $this->notifySuperAdmins(
            ($isBastSubmission ? 'Pengajuan Dokumen BAST Bantuan TJSL oleh ' : 'Pengajuan Bantuan TJSL oleh ')
                .($item->creator?->name ?? 'Admin'),
            $isBastSubmission
                ? 'Dokumen BAST bantuan "'.$item->nama_program_bantuan.'" menunggu persetujuan Super Admin.'
                : 'Bantuan "'.$item->nama_program_bantuan.'" menunggu persetujuan Super Admin.',
            'bantuan_csr',
            $item->id,
        );
    }

    private function notifySuperAdmins(string $title, string $message, string $type, int $id): void
    {
        User::where('role', 'super_admin')
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn ($userId) => AdminNotification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'related_type' => $type,
                'related_id' => $id,
            ]));
    }
}
