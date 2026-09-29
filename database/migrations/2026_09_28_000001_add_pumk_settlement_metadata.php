<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table): void {
            $table->string('lunas_reason', 32)->nullable()->after('lunas_note');
            $table->decimal('lunas_saldo_pokok', 18, 2)->nullable()->after('lunas_reason');
            $table->decimal('lunas_saldo_bunga', 18, 2)->nullable()->after('lunas_saldo_pokok');
            $table->decimal('lunas_total_saldo', 18, 2)->nullable()->after('lunas_saldo_bunga');
            $table->decimal('lunas_tolerance_applied', 18, 2)->nullable()->after('lunas_total_saldo');
        });
    }

    public function down(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table): void {
            $table->dropColumn([
                'lunas_reason', 'lunas_saldo_pokok', 'lunas_saldo_bunga',
                'lunas_total_saldo', 'lunas_tolerance_applied',
            ]);
        });
    }
};
