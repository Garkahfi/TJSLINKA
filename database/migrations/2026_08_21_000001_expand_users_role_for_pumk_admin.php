<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('admin')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'pumk_admin')->exists()) {
            throw new RuntimeException(
                'Rollback dibatalkan: hapus atau pindahkan akun role pumk_admin secara eksplisit agar tidak berubah menjadi Admin TJSL.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'super_admin'])->default('admin')->change();
        });
    }
};
