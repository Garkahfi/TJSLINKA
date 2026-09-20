<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_angsuran', function (Blueprint $table): void {
            $table->string('bukti_pembayaran_path', 500)->nullable()->after('nomor_bukti');
            $table->string('bukti_pembayaran_nama_asli', 255)->nullable()->after('bukti_pembayaran_path');
            $table->string('bukti_pembayaran_mime', 100)->nullable()->after('bukti_pembayaran_nama_asli');
            $table->unsignedBigInteger('bukti_pembayaran_size')->nullable()->after('bukti_pembayaran_mime');
            $table->timestamp('bukti_pembayaran_uploaded_at')->nullable()->after('bukti_pembayaran_size');
        });
    }

    public function down(): void
    {
        Schema::table('pumk_angsuran', function (Blueprint $table): void {
            $table->dropColumn([
                'bukti_pembayaran_path',
                'bukti_pembayaran_nama_asli',
                'bukti_pembayaran_mime',
                'bukti_pembayaran_size',
                'bukti_pembayaran_uploaded_at',
            ]);
        });
    }
};
