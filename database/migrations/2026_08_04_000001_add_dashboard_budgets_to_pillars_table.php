<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pillars', function (Blueprint $table) {
            $table->decimal('dashboard_rencana_anggaran', 15, 2)
                ->default(0)
                ->after('slug');
            $table->decimal('dashboard_realisasi_anggaran', 15, 2)
                ->default(0)
                ->after('dashboard_rencana_anggaran');
        });
    }

    public function down(): void
    {
        Schema::table('pillars', function (Blueprint $table) {
            $table->dropColumn([
                'dashboard_rencana_anggaran',
                'dashboard_realisasi_anggaran',
            ]);
        });
    }
};
