<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_depan')->nullable()->after('name');
            $table->string('nama_belakang')->nullable()->after('nama_depan');
            $table->string('no_telephone')->nullable()->after('email');
            $table->string('jabatan')->nullable()->after('no_telephone');
            $table->text('alamat')->nullable()->after('jabatan');
            $table->string('avatar_path')->nullable()->after('alamat');
            $table->enum('role', ['admin', 'super_admin'])->default('admin')->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['nama_depan', 'nama_belakang', 'no_telephone', 'jabatan', 'alamat', 'avatar_path', 'role']));
    }
};
