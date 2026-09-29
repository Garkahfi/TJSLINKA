<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL dapat meninggalkan tabel baru setelah DDL CREATE berhasil,
        // walaupun pembuatan indeks berikutnya gagal. Lanjutkan hanya jika
        // bentuk tabel sama dan belum berisi data; jangan menghapusnya.
        if (Schema::hasTable('pumk_classification_history')) {
            if (! Schema::hasColumns('pumk_classification_history', [
                'id', 'mitra_id', 'pinjaman_id', 'attribute', 'value', 'effective_from',
                'recorded_at', 'source_kind', 'source_ref', 'recorded_by',
                'fingerprint', 'created_at', 'updated_at',
            ]) || DB::table('pumk_classification_history')->exists()) {
                throw new RuntimeException('Tabel riwayat klasifikasi yang ada perlu diperiksa sebelum migrasi dilanjutkan.');
            }

            $this->ensureIndexes();

            return;
        }

        Schema::create('pumk_classification_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->nullable()->constrained('pumk_mitra')->restrictOnDelete();
            $table->foreignId('pinjaman_id')->nullable()->constrained('pumk_pinjaman')->restrictOnDelete();
            $table->string('attribute', 32);
            $table->string('value')->nullable();
            $table->date('effective_from');
            $table->dateTime('recorded_at');
            $table->string('source_kind', 32);
            $table->string('source_ref')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('fingerprint', 64)->unique('pumk_cls_fingerprint_uq');
            $table->timestamps();
            $table->index(['mitra_id', 'attribute', 'effective_from'], 'pumk_cls_mitra_attr_date_idx');
            $table->index(['pinjaman_id', 'attribute', 'effective_from'], 'pumk_cls_loan_attr_date_idx');
        });
    }

    private function ensureIndexes(): void
    {
        foreach ([
            'pumk_cls_mitra_attr_date_idx' => ['mitra_id', 'attribute', 'effective_from'],
            'pumk_cls_loan_attr_date_idx' => ['pinjaman_id', 'attribute', 'effective_from'],
        ] as $name => $columns) {
            if (! Schema::hasIndex('pumk_classification_history', $name)) {
                Schema::table('pumk_classification_history', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
        if (! Schema::hasIndex('pumk_classification_history', 'pumk_cls_fingerprint_uq')) {
            Schema::table('pumk_classification_history', fn (Blueprint $table) => $table->unique('fingerprint', 'pumk_cls_fingerprint_uq'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_classification_history');
    }
};
