<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_bri_rka_tahunan', function (Blueprint $table): void {
            $table->decimal('nominal_rka', 18, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pumk_bri_rka_tahunan', function (Blueprint $table): void {
            $table->decimal('nominal_rka', 18, 2)->nullable(false)->change();
        });
    }
};
