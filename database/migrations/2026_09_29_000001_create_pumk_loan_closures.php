<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_loan_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pinjaman_id')->constrained('pumk_pinjaman')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settlement_snapshot');
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_note')->nullable();
            $table->timestamps();
            $table->index(['pinjaman_id', 'closed_at']);
        });

        // Preserve pre-existing closures without changing loans, payments or balances.
        DB::table('pumk_pinjaman')->where('status', 'lunas')->whereNotNull('lunas_at')
            ->orderBy('id')->chunkById(200, function ($loans): void {
                foreach ($loans as $loan) {
                    $snapshot = array_intersect_key((array) $loan, array_flip([
                        'lunas_reason', 'lunas_note', 'lunas_saldo_pokok', 'lunas_saldo_bunga',
                        'lunas_total_saldo', 'lunas_tolerance_applied',
                    ]));
                    DB::table('pumk_loan_closures')->insert([
                        'pinjaman_id' => $loan->id, 'closed_at' => $loan->lunas_at,
                        'closed_by' => $loan->lunas_by, 'settlement_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_loan_closures');
    }
};
