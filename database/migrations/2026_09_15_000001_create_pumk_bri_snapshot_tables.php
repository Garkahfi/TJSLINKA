<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_bri_mitra', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_mitra');
            $table->text('alamat')->nullable();
            $table->string('wilayah')->nullable();
            $table->string('sektor_usaha')->nullable();
            $table->decimal('pinjaman', 18, 2)->nullable();
            $table->string('tenor_raw')->nullable();
            $table->date('tanggal_pencairan')->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->char('source_key', 64)->unique();
            $table->timestamps();
        });

        Schema::create('pumk_bri_snapshot_bulanan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained('pumk_bri_mitra')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('saldo_piutang', 18, 2)->default(0);
            $table->string('kolektibilitas_kode', 2)->nullable();
            $table->string('kolektibilitas_label', 32)->nullable();
            $table->string('source_sheet');
            $table->unsignedInteger('source_row');
            $table->unsignedInteger('no_urut_sumber')->nullable();
            $table->timestamps();

            $table->unique(['mitra_id', 'bulan', 'tahun'], 'pumk_bri_snapshot_mitra_period_unique');
            $table->index(['tahun', 'bulan'], 'pumk_bri_snapshot_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_bri_snapshot_bulanan');
        Schema::dropIfExists('pumk_bri_mitra');
    }
};
