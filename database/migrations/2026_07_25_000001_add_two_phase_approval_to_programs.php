<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();

            $table->foreignId('fase1_reviewed_by')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('fase1_reviewed_at')->nullable()->after('fase1_reviewed_by');
            $table->text('fase1_rejected_reason')->nullable()->after('fase1_reviewed_at');

            $table->foreignId('fase2_reviewed_by')
                ->nullable()
                ->after('fase1_rejected_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('fase2_reviewed_at')->nullable()->after('fase2_reviewed_by');
            $table->text('fase2_rejected_reason')->nullable()->after('fase2_reviewed_at');
        });

        Schema::table('program_documents', function (Blueprint $table) {
            $table->string('document_type', 64)->change();
        });

        DB::table('programs')
            ->where('status', 'approved')
            ->update([
                'status' => 'completed',
                'fase1_reviewed_by' => DB::raw('reviewed_by'),
                'fase1_reviewed_at' => DB::raw('reviewed_at'),
                'fase2_reviewed_by' => DB::raw('reviewed_by'),
                'fase2_reviewed_at' => DB::raw('reviewed_at'),
            ]);

        DB::table('programs')
            ->where('status', 'rejected')
            ->update([
                'status' => 'rejected_fase1',
                'fase1_reviewed_by' => DB::raw('reviewed_by'),
                'fase1_reviewed_at' => DB::raw('reviewed_at'),
                'fase1_rejected_reason' => DB::raw('rejected_reason'),
            ]);

        DB::table('programs')
            ->where('status', 'pending')
            ->update(['status' => 'pending_fase1']);
    }

    public function down(): void
    {
        DB::table('programs')
            ->whereIn('status', ['approved_fase1', 'pending_fase2', 'completed'])
            ->update(['status' => 'approved']);
        DB::table('programs')
            ->where('status', 'pending_fase1')
            ->update(['status' => 'pending']);
        DB::table('programs')
            ->where('status', 'rejected_fase1')
            ->update(['status' => 'rejected']);

        DB::table('program_documents')
            ->where('document_type', 'proposal_permintaan')
            ->update(['document_type' => 'surat_penawaran_balasan']);
        DB::table('program_documents')
            ->where('document_type', 'kelengkapan_survei')
            ->update(['document_type' => 'bukti_penjajakan']);

        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['fase1_reviewed_by']);
            $table->dropForeign(['fase2_reviewed_by']);
            $table->dropColumn([
                'fase1_reviewed_by',
                'fase1_reviewed_at',
                'fase1_rejected_reason',
                'fase2_reviewed_by',
                'fase2_reviewed_at',
                'fase2_rejected_reason',
            ]);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])
                ->default('draft')
                ->change();
        });

        Schema::table('program_documents', function (Blueprint $table) {
            $table->enum('document_type', [
                'surat_penawaran_balasan',
                'bukti_penjajakan',
                'kajian_kelayakan',
                'kajian_mitigasi_risiko',
                'perjanjian_kerja_sama',
                'bast',
                'lainnya',
            ])->change();
        });
    }
};
