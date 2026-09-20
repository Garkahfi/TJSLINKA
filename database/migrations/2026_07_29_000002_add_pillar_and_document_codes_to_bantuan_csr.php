<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bantuan_csr', function (Blueprint $table) {
            $table->foreignId('pillar_id')
                ->nullable()
                ->after('deskripsi_bantuan')
                ->constrained('pillars');
        });

        DB::table('bantuan_csr_documents')
            ->where('document_type', 'proposal_pengajuan_program')
            ->update(['document_type' => 'A']);

        DB::table('bantuan_csr_documents')
            ->where('document_type', 'kelengkapan_survei')
            ->update(['document_type' => 'B']);
    }

    public function down(): void
    {
        DB::table('bantuan_csr_documents')
            ->where('document_type', 'A')
            ->update(['document_type' => 'proposal_pengajuan_program']);

        DB::table('bantuan_csr_documents')
            ->where('document_type', 'B')
            ->update(['document_type' => 'kelengkapan_survei']);

        Schema::table('bantuan_csr', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pillar_id');
        });
    }
};
