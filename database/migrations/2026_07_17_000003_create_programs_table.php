<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('pillar_id')->constrained()->restrictOnDelete();
            $table->string('nama_program');
            $table->text('deskripsi_program');
            $table->text('sasaran_program');
            $table->text('lokasi_program');
            $table->text('mitra_program');
            $table->decimal('rencana_anggaran', 18, 2)->default(0);
            $table->decimal('realisasi_anggaran', 18, 2)->default(0);
            $table->text('tujuan_program');
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
        Schema::dropIfExists('programs');
    }
};
