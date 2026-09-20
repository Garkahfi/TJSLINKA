<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');
            $table->char('file_hash', 64)->unique();
            $table->date('tanggal_acuan')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('berhasil')->default(0);
            $table->unsignedInteger('gagal')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('pumk_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('pumk_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->char('row_hash', 64)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_row_number'], 'pumk_import_rows_batch_row_unique');
        });

        Schema::create('pumk_wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pumk_sektor_usaha', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_sektor_usaha');
        Schema::dropIfExists('pumk_wilayah');
        Schema::dropIfExists('pumk_import_rows');
        Schema::dropIfExists('pumk_import_batches');
    }
};
