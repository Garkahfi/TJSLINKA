<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_bri_fasilitas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained('pumk_bri_mitra')->cascadeOnDelete();
            $table->uuid('reference_key')->unique();
            $table->decimal('pinjaman', 18, 2)->nullable();
            $table->string('tenor_raw')->nullable();
            $table->date('tanggal_pencairan')->nullable();
            $table->timestamps();
            $table->index('mitra_id');
        });

        Schema::table('pumk_bri_snapshot_bulanan', function (Blueprint $table): void {
            $table->foreignId('fasilitas_id')->nullable()->after('mitra_id')->constrained('pumk_bri_fasilitas')->restrictOnDelete();
            $table->string('nama_mitra_sumber')->nullable();
            $table->text('alamat_sumber')->nullable();
            $table->string('wilayah_sumber')->nullable();
            $table->string('sektor_usaha_sumber')->nullable();
            $table->decimal('pinjaman_sumber', 18, 2)->nullable();
            $table->string('tenor_sumber')->nullable();
            $table->date('tanggal_pencairan_sumber')->nullable();
            $table->date('tanggal_jatuh_tempo_sumber')->nullable();
            $table->boolean('profil_sumber_terverifikasi')->default(false);
            $table->json('source_payload')->nullable();
            $table->index('mitra_id', 'pumk_bri_snapshot_mitra_index');
            $table->unique(['fasilitas_id', 'bulan', 'tahun'], 'pumk_bri_snapshot_fasilitas_period_unique');
        });

        Schema::create('pumk_bri_identity_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('source_sheet');
            $table->unsignedInteger('source_row');
            $table->json('source_profile');
            $table->char('source_fingerprint', 64);
            $table->json('candidate_fasilitas_ids')->nullable();
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->string('resolution_action')->nullable();
            $table->foreignId('resolved_fasilitas_id')->nullable()->constrained('pumk_bri_fasilitas')->nullOnDelete();
            $table->foreignId('resolved_mitra_id')->nullable()->constrained('pumk_bri_mitra')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tahun', 'bulan', 'source_sheet', 'source_row'], 'pumk_bri_review_source_unique');
        });

        // Legacy rows remain attached to the same surrogate IDs. The source name was
        // previously altered by the importer, so these backfilled profiles are marked
        // unverified until the original workbook is reviewed/re-imported.
        DB::table('pumk_bri_mitra')->orderBy('id')->chunkById(200, function ($mitraRows): void {
            foreach ($mitraRows as $mitra) {
                $fasilitasId = DB::table('pumk_bri_fasilitas')->insertGetId([
                    'mitra_id' => $mitra->id,
                    'reference_key' => (string) Str::uuid(),
                    'pinjaman' => $mitra->pinjaman,
                    'tenor_raw' => $mitra->tenor_raw,
                    'tanggal_pencairan' => $mitra->tanggal_pencairan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('pumk_bri_snapshot_bulanan')
                    ->where('mitra_id', $mitra->id)
                    ->update([
                        'fasilitas_id' => $fasilitasId,
                        'nama_mitra_sumber' => $mitra->nama_mitra,
                        'alamat_sumber' => $mitra->alamat,
                        'wilayah_sumber' => $mitra->wilayah,
                        'sektor_usaha_sumber' => $mitra->sektor_usaha,
                        'pinjaman_sumber' => $mitra->pinjaman,
                        'tenor_sumber' => $mitra->tenor_raw,
                        'tanggal_pencairan_sumber' => $mitra->tanggal_pencairan,
                        'tanggal_jatuh_tempo_sumber' => $mitra->tanggal_jatuh_tempo,
                    ]);
            }
        });

        Schema::table('pumk_bri_snapshot_bulanan', function (Blueprint $table): void {
            $table->dropUnique('pumk_bri_snapshot_mitra_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pumk_bri_snapshot_bulanan', function (Blueprint $table): void {
            $table->unique(['mitra_id', 'bulan', 'tahun'], 'pumk_bri_snapshot_mitra_period_unique');
            $table->dropUnique('pumk_bri_snapshot_fasilitas_period_unique');
            $table->dropConstrainedForeignId('fasilitas_id');
            $table->dropColumn([
                'nama_mitra_sumber', 'alamat_sumber', 'wilayah_sumber', 'sektor_usaha_sumber',
                'pinjaman_sumber', 'tenor_sumber', 'tanggal_pencairan_sumber',
                'tanggal_jatuh_tempo_sumber', 'profil_sumber_terverifikasi',
                'source_payload',
            ]);
            $table->dropIndex('pumk_bri_snapshot_mitra_index');
        });
        Schema::dropIfExists('pumk_bri_identity_reviews');
        Schema::dropIfExists('pumk_bri_fasilitas');
    }
};
