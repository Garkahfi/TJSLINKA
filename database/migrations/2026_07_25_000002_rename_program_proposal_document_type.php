<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('program_documents')
            ->where('document_type', 'proposal_permintaan')
            ->update(['document_type' => 'proposal_pengajuan_program']);
    }

    public function down(): void
    {
        DB::table('program_documents')
            ->where('document_type', 'proposal_pengajuan_program')
            ->update(['document_type' => 'proposal_permintaan']);
    }
};
