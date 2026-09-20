<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table): void {
            $table->string('status', 20)->default('aktif')->index()->after('is_active');
            $table->timestamp('lunas_at')->nullable()->index()->after('status');
            $table->foreignId('lunas_by')->nullable()->after('lunas_at')->constrained('users')->nullOnDelete();
            $table->text('lunas_note')->nullable()->after('lunas_by');
        });

        DB::table('pumk_pinjaman')->where('is_active', false)->update(['status' => 'nonaktif']);

        Schema::create('pumk_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->string('action', 64)->index();
            $table->string('module', 32)->index();
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('description_safe', 500);
            $table->json('metadata_safe')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_activity_logs');
        Schema::table('pumk_pinjaman', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lunas_by');
            $table->dropIndex(['status']);
            $table->dropIndex(['lunas_at']);
            $table->dropColumn(['status', 'lunas_at', 'lunas_note']);
        });
    }
};
