<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidang_prioritas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bidang')->unique();
            $table->decimal('rencana_anggaran', 15, 2)->nullable();
            $table->decimal('realisasi_anggaran', 15, 2)->nullable();
            $table->decimal('penyerapan_persen', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidang_prioritas');
    }
};
