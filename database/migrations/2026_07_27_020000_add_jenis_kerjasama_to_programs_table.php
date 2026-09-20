<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->enum('jenis_kerjasama', ['pks', 'non_pks'])
                ->nullable()
                ->after('pillar_id');
        });

        // Program Internal lama mengikuti alur enam dokumen, sehingga diperlakukan
        // sebagai PKS agar data yang sudah ada tidak berubah makna.
        DB::table('programs')
            ->whereNull('jenis_kerjasama')
            ->update(['jenis_kerjasama' => 'pks']);
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('jenis_kerjasama');
        });
    }
};
