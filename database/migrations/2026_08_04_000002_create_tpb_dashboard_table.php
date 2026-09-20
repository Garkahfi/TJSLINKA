<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpb_dashboard', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tpb')->unique();
            $table->string('nama_tpb')->nullable();
            $table->decimal('rencana_anggaran', 15, 2)->nullable();
            $table->decimal('realisasi_anggaran', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpb_dashboard');
    }
};
