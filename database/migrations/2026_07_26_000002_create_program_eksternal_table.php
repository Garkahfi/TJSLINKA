<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('related_type', 32)->change();
        });

        Schema::table('status_logs', function (Blueprint $table) {
            $table->string('related_type', 32)->change();
        });

        Schema::create('program_eksternal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->string('status', 32)->default('draft')->index();

            $table->string('no_reg')->nullable();
            $table->date('tanggal_diterima')->nullable();
            $table->string('asal_proposal')->nullable();
            $table->string('kategori_instansi')->nullable();
            $table->string('no_surat_pengantar')->nullable();
            $table->text('perihal')->nullable();

            $table->text('kebutuhan')->nullable();
            $table->enum('jenis_bantuan_diminta', ['tunai', 'barang_jasa'])->nullable();
            $table->decimal('total_kebutuhan_dana', 15, 2)->nullable();
            $table->enum('sifat_pengajuan', ['urgent', 'biasa'])->nullable();
            $table->date('deadline')->nullable();

            $table->string('contact_person')->nullable();
            $table->string('nama_bank')->nullable();
            $table->string('no_rekening')->nullable();

            $table->enum('kesesuaian_program_rka', ['ada', 'tidak_ada'])->nullable();
            $table->foreignId('pillar_id')->nullable()->constrained('pillars');
            $table->string('tpb')->nullable();
            $table->string('program')->nullable();
            $table->text('keterangan_kesesuaian')->nullable();

            $table->decimal('anggaran_program_tjsl', 15, 2)->nullable();
            $table->decimal('dana_sudah_digunakan', 15, 2)->nullable();
            $table->decimal('sisa_anggaran', 15, 2)->nullable();

            $table->text('uraian_tindak_lanjut')->nullable();
            $table->enum('survey_diperlukan', ['iya', 'tidak'])->nullable();
            $table->date('deadline_tindak_lanjut')->nullable();

            $table->foreignId('kajian_reviewed_by')->nullable()->constrained('users');
            $table->timestamp('kajian_reviewed_at')->nullable();
            $table->text('kajian_rejected_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_eksternal');

        DB::table('notifications')->where('related_type', 'program_eksternal')->delete();
        DB::table('status_logs')->where('related_type', 'program_eksternal')->delete();

        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('related_type', ['program', 'bantuan_csr'])->change();
        });

        Schema::table('status_logs', function (Blueprint $table) {
            $table->enum('related_type', ['program', 'bantuan_csr'])->change();
        });
    }
};
