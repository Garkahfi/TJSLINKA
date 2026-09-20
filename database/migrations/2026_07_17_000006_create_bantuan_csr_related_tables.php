<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bantuan_csr_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bantuan_csr_id')->constrained('bantuan_csr')->cascadeOnDelete();
            $table->enum('document_type', ['proposal_permintaan', 'formulir_kajian_proposal', 'formulir_survei', 'laporan_hasil_survei', 'formulir_persetujuan', 'dokumentasi_survei', 'lainnya']);
            $table->string('nama_dokumen');
            $table->string('file_path');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
        Schema::create('bantuan_csr_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bantuan_csr_id')->constrained('bantuan_csr')->cascadeOnDelete();
            $table->text('target_text');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
        Schema::create('bantuan_csr_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bantuan_csr_id')->constrained('bantuan_csr')->cascadeOnDelete();
            $table->text('rincian_kegiatan');
            $table->string('penerima_bantuan');
            $table->string('jenis_bantuan');
            $table->string('quality');
            $table->decimal('nominal_bantuan', 18, 2)->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
        Schema::create('bantuan_csr_detail_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bantuan_csr_detail_id')->constrained('bantuan_csr_details')->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bantuan_csr_detail_photos');
        Schema::dropIfExists('bantuan_csr_details');
        Schema::dropIfExists('bantuan_csr_targets');
        Schema::dropIfExists('bantuan_csr_documents');
    }
};
