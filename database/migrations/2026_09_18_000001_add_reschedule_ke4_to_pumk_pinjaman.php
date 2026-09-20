<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table) {
            $table->string('reschedule_ke4')->nullable()->after('reschedule_ke3');
        });
    }

    public function down(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table) {
            $table->dropColumn('reschedule_ke4');
        });
    }
};
