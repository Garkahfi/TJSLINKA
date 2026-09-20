<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_bri_rka_tahunan', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->decimal('nominal_rka', 18, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pumk_bri_penyaluran_bulanan', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('nominal_penyaluran', 18, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tahun', 'bulan'], 'pumk_bri_penyaluran_tahun_bulan_unique');
            $table->index(['tahun', 'bulan'], 'pumk_bri_penyaluran_period_index');
        });

        Schema::table('pumk_bri_fasilitas', function (Blueprint $table): void {
            $table->string('sektor_usaha')->nullable()->after('tenor_raw');
            $table->string('status', 20)->default('aktif')->after('tanggal_pencairan');
            $table->date('first_seen_period')->nullable()->after('status');
            $table->date('last_seen_period')->nullable()->after('first_seen_period');
            $table->index('status', 'pumk_bri_fasilitas_status_index');
        });

        $latestPeriod = DB::table('pumk_bri_snapshot_bulanan')
            ->selectRaw('MAX((tahun * 100) + bulan) as period_key')
            ->where('profil_sumber_terverifikasi', true)
            ->value('period_key');

        DB::table('pumk_bri_fasilitas')->orderBy('id')->chunkById(200, function ($facilities) use ($latestPeriod): void {
            foreach ($facilities as $facility) {
                $snapshots = DB::table('pumk_bri_snapshot_bulanan')
                    ->where('fasilitas_id', $facility->id)
                    ->orderBy('tahun')
                    ->orderBy('bulan')
                    ->get(['tahun', 'bulan', 'saldo_piutang', 'sektor_usaha_sumber']);

                if ($snapshots->isEmpty()) {
                    continue;
                }

                $first = $snapshots->first();
                $last = $snapshots->last();
                $lastPeriod = ((int) $last->tahun * 100) + (int) $last->bulan;
                $status = (float) $last->saldo_piutang <= 0
                    || ($latestPeriod !== null && $lastPeriod < (int) $latestPeriod)
                        ? 'lunas'
                        : 'aktif';

                $sectorSnapshot = $snapshots->first(
                    static fn (object $snapshot): bool => filled($snapshot->sektor_usaha_sumber),
                );

                DB::table('pumk_bri_fasilitas')->where('id', $facility->id)->update([
                    'sektor_usaha' => $sectorSnapshot?->sektor_usaha_sumber,
                    'status' => $status,
                    'first_seen_period' => sprintf('%04d-%02d-01', $first->tahun, $first->bulan),
                    'last_seen_period' => sprintf('%04d-%02d-01', $last->tahun, $last->bulan),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pumk_bri_fasilitas', function (Blueprint $table): void {
            $table->dropIndex('pumk_bri_fasilitas_status_index');
            $table->dropColumn(['sektor_usaha', 'status', 'first_seen_period', 'last_seen_period']);
        });

        Schema::dropIfExists('pumk_bri_penyaluran_bulanan');
        Schema::dropIfExists('pumk_bri_rka_tahunan');
    }
};
