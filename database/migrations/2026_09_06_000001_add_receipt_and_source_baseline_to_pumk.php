<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_angsuran', function (Blueprint $table) {
            $table->string('nomor_bukti')->nullable()->after('periode');
        });
        Schema::table('pumk_pinjaman', function (Blueprint $table) {
            // Pisahkan snapshot Excel dari cache yang berubah saat pembayaran.
            $table->json('baseline_sumber')->nullable();
        });

        DB::table('pumk_pinjaman')->whereNotNull('source_updated_at')->orderBy('id')
            ->chunkById(100, function ($loans): void {
                foreach ($loans as $loan) {
                    $opening = DB::table('pumk_saldo_awal')->where('pinjaman_id', $loan->id)->first();
                    $payments = DB::table('pumk_angsuran')->where('pinjaman_id', $loan->id)
                        ->where(function ($query) use ($loan): void {
                            $query->whereNotNull('batch_id')->orWhereNull('created_at')
                                ->orWhere('created_at', '<=', $loan->source_updated_at);
                        })->selectRaw('SUM(pokok) as pokok, SUM(bunga) as bunga, SUM(denda) as denda')->first();
                    $baseline = [];
                    foreach (['sisa_pokok', 'sisa_bunga', 'bulan_tunggakan', 'nilai_tunggakan', 'kolektibilitas'] as $field) {
                        $baseline[$field] = $loan->$field;
                    }
                    foreach (['pokok' => 'pokok_masuk', 'bunga' => 'bunga_masuk', 'denda' => 'denda'] as $field => $openingField) {
                        $baseline['total_'.$field.'_masuk'] = bcadd((string) ($opening?->$openingField ?? 0), (string) ($payments->$field ?? 0), 2);
                    }
                    DB::table('pumk_pinjaman')->where('id', $loan->id)->update(['baseline_sumber' => json_encode($baseline)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pumk_angsuran', fn (Blueprint $table) => $table->dropColumn('nomor_bukti'));
        Schema::table('pumk_pinjaman', fn (Blueprint $table) => $table->dropColumn('baseline_sumber'));
    }
};
