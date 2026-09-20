<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_bri_ringkasan', function (Blueprint $table): void {
            $table->id();
            $table->decimal('rka_tahun_ini', 18, 2)->default(0);
            $table->decimal('realisasi_sd_desember', 18, 2)->default(0);
            $table->decimal('progres_kolaborasi_persen', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pumk_bri_sektor', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_sektor')->unique();
            $table->decimal('nilai_portofolio', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pumk_bri_kualitas', function (Blueprint $table): void {
            $table->id();
            $table->string('kategori')->unique();
            $table->decimal('nilai', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pumk_bri_saldo_bulanan', function (Blueprint $table): void {
            $table->id();
            $table->string('sektor_atau_kategori');
            $table->string('tipe', 24);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('nilai', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['sektor_atau_kategori', 'tipe', 'bulan', 'tahun'],
                'pumk_bri_saldo_period_category_unique',
            );
            $table->index(['tahun', 'bulan', 'tipe'], 'pumk_bri_saldo_period_type_index');
        });

        Schema::create('pumk_snapshot_bulanan', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('kategori');
            $table->string('tipe', 24);
            $table->decimal('nilai', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['bulan', 'tahun', 'kategori', 'tipe'],
                'pumk_snapshot_period_category_unique',
            );
            $table->index(['tahun', 'bulan', 'tipe'], 'pumk_snapshot_period_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_snapshot_bulanan');
        Schema::dropIfExists('pumk_bri_saldo_bulanan');
        Schema::dropIfExists('pumk_bri_kualitas');
        Schema::dropIfExists('pumk_bri_sektor');
        Schema::dropIfExists('pumk_bri_ringkasan');
    }
};
