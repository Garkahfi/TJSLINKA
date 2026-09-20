<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teras_produk', function (Blueprint $table) {
            $table->id();
            $table->string('nama_produk');
            $table->string('nama_umkm');
            $table->string('foto_path')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('teras_paket', function (Blueprint $table) {
            $table->id();
            $table->string('nama_paket');
            $table->string('foto_path')->nullable();
            $table->decimal('harga', 15, 2);
            $table->enum('tipe_harga', ['tetap', 'maksimal'])->default('tetap');
            $table->json('isi_paket');
            $table->text('catatan_khusus')->nullable();
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teras_paket');
        Schema::dropIfExists('teras_produk');
    }
};
