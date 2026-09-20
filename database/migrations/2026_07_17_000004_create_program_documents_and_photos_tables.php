<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['surat_penawaran_balasan', 'bukti_penjajakan', 'kajian_kelayakan', 'kajian_mitigasi_risiko', 'perjanjian_kerja_sama', 'bast', 'lainnya']);
            $table->string('nama_dokumen');
            $table->string('file_path');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
        Schema::create('program_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_photos');
        Schema::dropIfExists('program_documents');
    }
};
