<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_histori_tahun', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pinjaman_id')->constrained('pumk_pinjaman')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('status', 24)->default('belum_dilengkapi')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['pinjaman_id', 'tahun']);
        });

        Schema::create('pumk_pinjaman_dokumen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pinjaman_id')->constrained('pumk_pinjaman')->cascadeOnDelete();
            $table->string('jenis_dokumen', 32);
            $table->string('file_path', 500);
            $table->string('nama_file_asli', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->unique(['pinjaman_id', 'jenis_dokumen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_pinjaman_dokumen');
        Schema::dropIfExists('pumk_histori_tahun');
    }
};
