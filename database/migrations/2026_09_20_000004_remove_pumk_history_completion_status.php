<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pumk_histori_tahun');
    }

    public function down(): void
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
    }
};
