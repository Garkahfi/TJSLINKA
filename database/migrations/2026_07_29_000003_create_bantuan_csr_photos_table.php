<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bantuan_csr_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bantuan_csr_id')
                ->constrained('bantuan_csr')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bantuan_csr_photos');
    }
};
