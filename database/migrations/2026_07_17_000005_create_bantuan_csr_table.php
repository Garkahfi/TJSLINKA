<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bantuan_csr', function (Blueprint $table) {
            $table->id();
            $table->string('nama_program_bantuan');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->text('deskripsi_bantuan');
            $table->decimal('rencana_anggaran', 18, 2)->default(0);
            $table->decimal('realisasi_anggaran', 18, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft')->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->text('rejected_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bantuan_csr');
    }
};
