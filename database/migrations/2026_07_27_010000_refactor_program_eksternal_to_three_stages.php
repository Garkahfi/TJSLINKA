<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_eksternal', function (Blueprint $table) {
            $table->dropForeign(['kajian_reviewed_by']);
        });

        Schema::table('program_eksternal', function (Blueprint $table) {
            $table->renameColumn('deadline', 'deadline_kebutuhan');
            $table->renameColumn('keterangan_kesesuaian', 'keterangan');
            $table->renameColumn('kajian_reviewed_by', 'tahap2_reviewed_by');
            $table->renameColumn('kajian_reviewed_at', 'tahap2_reviewed_at');
            $table->renameColumn('kajian_rejected_reason', 'tahap2_tolak_reason');
        });

        Schema::table('program_eksternal', function (Blueprint $table) {
            $table->foreign('tahap2_reviewed_by')
                ->references('id')
                ->on('users');

            $table->foreignId('tahap3_reviewed_by')
                ->nullable()
                ->after('tahap2_tolak_reason')
                ->constrained('users');
            $table->timestamp('tahap3_reviewed_at')
                ->nullable()
                ->after('tahap3_reviewed_by');
        });

        DB::table('program_eksternal')
            ->where('status', 'pending_kajian')
            ->update(['status' => 'pending_tahap2']);

        DB::table('program_eksternal')
            ->where('status', 'rejected_kajian')
            ->update(['status' => 'ditolak_tahap2']);

        DB::table('program_eksternal')
            ->where('status', 'approved_kajian')
            ->update(['status' => 'pending_tahap3']);

        DB::table('program_eksternal')
            ->whereIn('status', ['draft', 'pending_tahap2'])
            ->update([
                'deadline_kebutuhan' => null,
                'keterangan' => null,
                'anggaran_program_tjsl' => null,
                'dana_sudah_digunakan' => null,
                'sisa_anggaran' => null,
                'tahap2_reviewed_by' => null,
                'tahap2_reviewed_at' => null,
                'tahap2_tolak_reason' => null,
                'uraian_tindak_lanjut' => null,
                'survey_diperlukan' => null,
                'deadline_tindak_lanjut' => null,
                'tahap3_reviewed_by' => null,
                'tahap3_reviewed_at' => null,
            ]);

        DB::table('program_eksternal')
            ->whereIn('status', ['ditolak_tahap2', 'pending_tahap3'])
            ->update([
                'uraian_tindak_lanjut' => null,
                'survey_diperlukan' => null,
                'deadline_tindak_lanjut' => null,
                'tahap3_reviewed_by' => null,
                'tahap3_reviewed_at' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('program_eksternal')
            ->where('status', 'pending_tahap2')
            ->update(['status' => 'pending_kajian']);

        DB::table('program_eksternal')
            ->where('status', 'ditolak_tahap2')
            ->update(['status' => 'rejected_kajian']);

        DB::table('program_eksternal')
            ->whereIn('status', ['pending_tahap3', 'selesai_tahap3'])
            ->update(['status' => 'approved_kajian']);

        Schema::table('program_eksternal', function (Blueprint $table) {
            $table->dropForeign(['tahap2_reviewed_by']);
            $table->dropForeign(['tahap3_reviewed_by']);
            $table->dropColumn([
                'tahap3_reviewed_by',
                'tahap3_reviewed_at',
            ]);
        });

        Schema::table('program_eksternal', function (Blueprint $table) {
            $table->renameColumn('deadline_kebutuhan', 'deadline');
            $table->renameColumn('keterangan', 'keterangan_kesesuaian');
            $table->renameColumn('tahap2_reviewed_by', 'kajian_reviewed_by');
            $table->renameColumn('tahap2_reviewed_at', 'kajian_reviewed_at');
            $table->renameColumn('tahap2_tolak_reason', 'kajian_rejected_reason');
        });

        Schema::table('program_eksternal', function (Blueprint $table) {
            $table->foreign('kajian_reviewed_by')
                ->references('id')
                ->on('users');
        });
    }
};
