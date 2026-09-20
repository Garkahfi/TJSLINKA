<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('program_documents')
            ->where('document_type', 'proposal_pengajuan_program')
            ->where('nama_dokumen', 'Proposal Permintaan Bantuan')
            ->update(['nama_dokumen' => 'Proposal Pengajuan Program']);

        DB::table('notifications')
            ->where('related_type', 'program')
            ->orderBy('id')
            ->each(function (object $notification): void {
                $title = preg_replace(
                    '/^Pengajuan Program TJSL Fase 2 \(BAST\) oleh /',
                    'Pengajuan Dokumen BAST Program TJSL oleh ',
                    $notification->title,
                );
                $title = preg_replace(
                    '/^Pengajuan Program TJSL Fase 1 oleh /',
                    'Pengajuan Program TJSL oleh ',
                    $title,
                );

                $message = preg_replace(
                    '/^Program "(.+)" menunggu peninjauan Fase 2 \(BAST\)\.$/',
                    'Dokumen BAST program "$1" menunggu persetujuan Super Admin.',
                    $notification->message,
                );
                $message = preg_replace(
                    '/^Program "(.+)" menunggu peninjauan Fase 1\.$/',
                    'Program "$1" menunggu persetujuan Super Admin.',
                    $message,
                );

                if ($title !== $notification->title || $message !== $notification->message) {
                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update([
                            'title' => $title,
                            'message' => $message,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('program_documents')
            ->where('document_type', 'proposal_pengajuan_program')
            ->where('nama_dokumen', 'Proposal Pengajuan Program')
            ->update(['nama_dokumen' => 'Proposal Permintaan Bantuan']);

        DB::table('notifications')
            ->where('related_type', 'program')
            ->orderBy('id')
            ->each(function (object $notification): void {
                $title = preg_replace(
                    '/^Pengajuan Dokumen BAST Program TJSL oleh /',
                    'Pengajuan Program TJSL Fase 2 (BAST) oleh ',
                    $notification->title,
                );
                $title = preg_replace(
                    '/^Pengajuan Program TJSL oleh /',
                    'Pengajuan Program TJSL Fase 1 oleh ',
                    $title,
                );

                $message = preg_replace(
                    '/^Dokumen BAST program "(.+)" menunggu persetujuan Super Admin\.$/',
                    'Program "$1" menunggu peninjauan Fase 2 (BAST).',
                    $notification->message,
                );
                $message = preg_replace(
                    '/^Program "(.+)" menunggu persetujuan Super Admin\.$/',
                    'Program "$1" menunggu peninjauan Fase 1.',
                    $message,
                );

                if ($title !== $notification->title || $message !== $notification->message) {
                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update([
                            'title' => $title,
                            'message' => $message,
                        ]);
                }
            });
    }
};
