<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['admin', 'super_admin', 'pumk_admin'])
            ->where('is_active', true)
            ->update(['must_change_password' => true]);
    }

    public function down(): void
    {
        // Status ini tidak dikembalikan ke false agar password sementara tidak
        // aktif kembali secara tidak sengaja ketika migration di-rollback.
    }
};
