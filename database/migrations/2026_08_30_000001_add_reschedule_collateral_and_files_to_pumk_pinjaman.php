<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table) {
            $table->string('reschedule_ke1')->nullable()->after('spj_awal');
            $table->string('reschedule_ke2')->nullable()->after('reschedule_ke1');
            $table->string('reschedule_ke3')->nullable()->after('reschedule_ke2');

            $table->text('jenis_jaminan')->nullable()->after('reschedule_ke3');
            $table->string('jaminan_no_pol')->nullable()->after('jenis_jaminan');
            $table->string('jaminan_no_bpkb')->nullable()->after('jaminan_no_pol');
            $table->string('jaminan_merk')->nullable()->after('jaminan_no_bpkb');
            $table->string('jaminan_type')->nullable()->after('jaminan_merk');
            $table->string('jaminan_tahun_kendaraan')->nullable()->after('jaminan_type');
            $table->string('jaminan_no_sertifikat')->nullable()->after('jaminan_tahun_kendaraan');
            $table->string('jaminan_luas')->nullable()->after('jaminan_no_sertifikat');
            $table->string('jaminan_atas_nama')->nullable()->after('jaminan_luas');
            $table->text('jaminan_alamat')->nullable()->after('jaminan_atas_nama');

            $table->string('berkas_spj_path')->nullable()->after('jaminan_alamat');
            $table->string('berkas_jaminan_path')->nullable()->after('berkas_spj_path');

            // Nilai ini merupakan turunan dari tanggal pencairan dan juga tersedia
            // sebagai kolom tersendiri pada berkas sumber final.
            $table->unsignedSmallInteger('tahun_pencairan')->nullable()->after('tanggal_pencairan');
        });
    }

    public function down(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table) {
            $table->dropColumn([
                'reschedule_ke1',
                'reschedule_ke2',
                'reschedule_ke3',
                'jenis_jaminan',
                'jaminan_no_pol',
                'jaminan_no_bpkb',
                'jaminan_merk',
                'jaminan_type',
                'jaminan_tahun_kendaraan',
                'jaminan_no_sertifikat',
                'jaminan_luas',
                'jaminan_atas_nama',
                'jaminan_alamat',
                'berkas_spj_path',
                'berkas_jaminan_path',
                'tahun_pencairan',
            ]);
        });
    }
};
