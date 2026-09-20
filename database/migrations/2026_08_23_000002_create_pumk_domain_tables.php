<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_mitra', function (Blueprint $table) {
            $table->id();
            $table->string('nama_mitra')->index();
            $table->string('jenis_usaha')->nullable();
            $table->foreignId('sektor_usaha_id')->nullable()->constrained('pumk_sektor_usaha')->nullOnDelete();
            $table->string('sektor_sumber')->nullable();
            $table->foreignId('wilayah_id')->nullable()->constrained('pumk_wilayah')->nullOnDelete();
            $table->string('wilayah_sumber')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_pemilik')->nullable();
            $table->text('no_ktp_encrypted')->nullable();
            $table->char('no_ktp_hash', 64)->nullable()->index();
            $table->text('no_telepon_encrypted')->nullable();
            $table->text('no_rekening_encrypted')->nullable();
            $table->char('source_key', 64)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pumk_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('pumk_mitra')->restrictOnDelete();
            $table->unsignedInteger('no_urut_sumber')->nullable();
            $table->string('spj_awal')->nullable()->index();
            $table->date('tanggal_pencairan')->nullable()->index();
            $table->date('mulai_angsuran')->nullable();
            $table->date('selesai_angsuran')->nullable();
            $table->decimal('pinjaman_pokok', 18, 2)->nullable();
            $table->decimal('persen_bunga', 8, 4)->nullable();
            $table->decimal('pinjaman_bunga', 18, 2)->nullable();
            $table->decimal('total_pinjaman', 18, 2)->nullable();
            $table->decimal('nilai_angsuran_bulanan', 18, 2)->nullable();
            $table->integer('bulan_tunggakan')->nullable();
            $table->decimal('nilai_tunggakan', 18, 2)->nullable();
            $table->string('kolektibilitas', 32)->nullable()->index();
            $table->decimal('sisa_pokok', 18, 2)->nullable();
            $table->decimal('sisa_bunga', 18, 2)->nullable();
            $table->decimal('total_sisa', 18, 2)->nullable();
            $table->char('source_key', 64)->unique();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pumk_saldo_awal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pinjaman_id')->unique()->constrained('pumk_pinjaman')->restrictOnDelete();
            $table->date('cutoff_date')->index();
            $table->decimal('pokok_masuk', 18, 2)->nullable();
            $table->decimal('bunga_masuk', 18, 2)->nullable();
            $table->decimal('denda', 18, 2)->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('pumk_import_batches')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pumk_angsuran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pinjaman_id')->constrained('pumk_pinjaman')->restrictOnDelete();
            $table->date('periode')->index();
            $table->decimal('pokok', 18, 2)->default(0);
            $table->decimal('bunga', 18, 2)->default(0);
            $table->decimal('denda', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('batch_id')->nullable()->constrained('pumk_import_batches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pinjaman_id', 'periode'], 'pumk_angsuran_pinjaman_periode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_angsuran');
        Schema::dropIfExists('pumk_saldo_awal');
        Schema::dropIfExists('pumk_pinjaman');
        Schema::dropIfExists('pumk_mitra');
    }
};
