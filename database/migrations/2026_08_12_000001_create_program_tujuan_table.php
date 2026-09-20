<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_tujuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('foto_path')->nullable();
            $table->text('deskripsi');
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->text('tujuan_program')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_tujuan');

        DB::table('programs')->whereNull('tujuan_program')->update(['tujuan_program' => '']);

        Schema::table('programs', function (Blueprint $table) {
            $table->text('tujuan_program')->nullable(false)->change();
        });
    }
};
