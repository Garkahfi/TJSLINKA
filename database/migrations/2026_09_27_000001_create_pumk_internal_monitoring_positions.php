<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_monitoring_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('as_of_date')->unique();
            $table->dateTime('source_updated_at')->nullable();
            $table->char('source_hash', 64);
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('known_loans')->default(0);
            $table->unsignedInteger('unknown_loans')->default(0);
            $table->boolean('needs_reconcile')->default(false);
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('pumk_monitoring_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('pumk_monitoring_reports')->cascadeOnDelete();
            $table->foreignId('pinjaman_id')->constrained('pumk_pinjaman')->restrictOnDelete();
            $table->foreignId('mitra_id')->constrained('pumk_mitra')->restrictOnDelete();
            $table->decimal('saldo_pokok', 18, 2);
            $table->decimal('saldo_bunga', 18, 2);
            $table->string('sektor')->nullable();
            $table->string('wilayah')->nullable();
            $table->string('kolektibilitas', 32)->nullable();
            $table->boolean('classification_limited')->default(false);
            $table->string('source_kind', 32);
            $table->timestamps();

            $table->unique(['report_id', 'pinjaman_id'], 'pumk_monitoring_report_loan_unique');
            $table->index(['mitra_id', 'report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_monitoring_positions');
        Schema::dropIfExists('pumk_monitoring_reports');
    }
};
