<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_bri_ringkasan', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tahun')->nullable()->after('id');
        });

        // The only existing BRI source period in this installation is 2026.
        // Future imports must supply their own year instead of relying on this
        // legacy backfill.
        DB::table('pumk_bri_ringkasan')->whereNull('tahun')->update(['tahun' => 2026]);

        Schema::table('pumk_bri_ringkasan', function (Blueprint $table): void {
            $table->unique('tahun', 'pumk_bri_ringkasan_tahun_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pumk_bri_ringkasan', function (Blueprint $table): void {
            $table->dropUnique('pumk_bri_ringkasan_tahun_unique');
            $table->dropColumn('tahun');
        });
    }
};
