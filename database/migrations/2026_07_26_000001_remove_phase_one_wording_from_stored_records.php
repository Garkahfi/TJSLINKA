<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('related_type', 'program')
            ->orderBy('id')
            ->each(function (object $notification): void {
                $title = str_replace(
                    'Pengajuan Program TJSL Fase 1 Ditolak',
                    'Pengajuan Program TJSL Ditolak',
                    $notification->title,
                );
                $message = preg_replace(
                    '/^Fase 1 program /',
                    'Program ',
                    $notification->message,
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

        DB::table('status_logs')
            ->where('related_type', 'program')
            ->where('note', 'Pengajuan Fase 1 dibatalkan Admin')
            ->update(['note' => 'Pengajuan dibatalkan Admin']);
    }

    public function down(): void
    {
        DB::table('notifications')
            ->where('related_type', 'program')
            ->where('title', 'Pengajuan Program TJSL Ditolak')
            ->update(['title' => 'Pengajuan Program TJSL Fase 1 Ditolak']);

        DB::table('status_logs')
            ->where('related_type', 'program')
            ->where('note', 'Pengajuan dibatalkan Admin')
            ->update(['note' => 'Pengajuan Fase 1 dibatalkan Admin']);
    }
};
