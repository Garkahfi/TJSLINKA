<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wilayah_operasional', function (Blueprint $table) {
            $table->decimal('realisasi_anggaran', 15, 2)
                ->default(0)
                ->after('longitude');
        });

        DB::table('wilayah_operasional')
            ->where('nama', 'Kota Madiun')
            ->update(['realisasi_anggaran' => 1092580101]);
        DB::table('wilayah_operasional')
            ->where('nama', 'Kab. Madiun')
            ->update(['realisasi_anggaran' => 751491882]);
        DB::table('wilayah_operasional')
            ->where('nama', 'Kab. Banyuwangi')
            ->update(['realisasi_anggaran' => 616280152]);
        DB::table('wilayah_operasional')
            ->where('nama', 'Wilayah Lainnya')
            ->update(['realisasi_anggaran' => 94845716]);
        DB::table('wilayah_operasional')
            ->where('nama', 'Kab. Ngawi')
            ->update(['realisasi_anggaran' => 52454600]);
        DB::table('wilayah_operasional')
            ->where('nama', 'Kab. Magetan')
            ->update(['realisasi_anggaran' => 11006500]);
        DB::table('wilayah_operasional')
            ->where('nama', 'Kab. Ponorogo')
            ->update(['realisasi_anggaran' => 5002500]);

        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_id');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('wilayah_id')
                ->nullable()
                ->after('lokasi_program')
                ->constrained('wilayah_operasional')
                ->nullOnDelete();
        });

        Schema::table('wilayah_operasional', function (Blueprint $table) {
            $table->dropColumn('realisasi_anggaran');
        });
    }
};
