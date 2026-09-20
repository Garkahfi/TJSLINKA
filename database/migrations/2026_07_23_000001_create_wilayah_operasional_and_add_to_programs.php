<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_operasional', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('wilayah_id')
                ->nullable()
                ->after('lokasi_program')
                ->constrained('wilayah_operasional')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_id');
        });

        Schema::dropIfExists('wilayah_operasional');
    }
};
