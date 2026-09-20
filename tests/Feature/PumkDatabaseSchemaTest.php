<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkImportBatch;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use Database\Seeders\PumkReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PumkDatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_normalized_pumk_tables_and_critical_columns_exist(): void
    {
        foreach ([
            'pumk_import_batches',
            'pumk_import_rows',
            'pumk_wilayah',
            'pumk_sektor_usaha',
            'pumk_mitra',
            'pumk_pinjaman',
            'pumk_saldo_awal',
            'pumk_angsuran',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ditemukan.");
        }

        $this->assertTrue(Schema::hasColumns('pumk_mitra', [
            'no_ktp_encrypted',
            'no_ktp_hash',
            'no_telepon_encrypted',
            'no_rekening_encrypted',
            'source_key',
        ]));
        $this->assertTrue(Schema::hasColumns('pumk_saldo_awal', [
            'pinjaman_id',
            'cutoff_date',
            'pokok_masuk',
            'bunga_masuk',
            'denda',
        ]));
        $this->assertTrue(Schema::hasColumns('pumk_pinjaman', [
            'reschedule_ke1',
            'reschedule_ke2',
            'reschedule_ke3',
            'reschedule_ke4',
            'jenis_jaminan',
            'jaminan_no_pol',
            'jaminan_no_bpkb',
            'jaminan_merk',
            'jaminan_type',
            'jaminan_tahun_kendaraan',
            'jaminan_no_sertifikat',
            'jaminan_luas',
            'jaminan_atas_nama',
            'jaminan_alamat',
            'berkas_spj_path',
            'berkas_jaminan_path',
            'tahun_pencairan',
            'baseline_sumber',
        ]));
        $this->assertTrue(Schema::hasColumn('pumk_angsuran', 'nomor_bukti'));
    }

    public function test_reference_seeder_is_idempotent_and_matches_the_eleven_excel_sectors(): void
    {
        $seeder = app(PumkReferenceSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('pumk_sektor_usaha', 11);
        $this->assertDatabaseHas('pumk_sektor_usaha', [
            'slug' => 'industri-makanan-minuman',
            'nama' => 'Industri - Makanan Minuman',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('pumk_sektor_usaha', [
            'slug' => 'industri-bengkel-pertukangan',
            'nama' => 'Industri - Bengkel / Pertukangan',
        ]);
    }

    public function test_pii_is_encrypted_and_financial_relationships_preserve_the_opening_balance(): void
    {
        $sector = PumkSektorUsaha::create([
            'nama' => 'Perdagangan',
            'slug' => 'perdagangan',
        ]);
        $wilayah = PumkWilayah::create([
            'nama' => 'Kabupaten Contoh',
            'slug' => 'kabupaten-contoh',
        ]);

        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Pengujian',
            'sektor_usaha_id' => $sector->id,
            'wilayah_id' => $wilayah->id,
            'no_ktp_encrypted' => '1234567890123456',
            'no_telepon_encrypted' => '081234567890',
            'no_rekening_encrypted' => '9876543210',
            'source_key' => hash('sha256', 'mitra-pengujian'),
        ]);

        $rawMitra = DB::table('pumk_mitra')->where('id', $mitra->id)->first();
        $this->assertNotSame('1234567890123456', $rawMitra->no_ktp_encrypted);
        $this->assertNotSame('081234567890', $rawMitra->no_telepon_encrypted);
        $this->assertSame('1234567890123456', $mitra->fresh()->no_ktp_encrypted);
        $this->assertNotNull($rawMitra->no_ktp_hash);

        $batch = PumkImportBatch::create([
            'nama_file' => 'uji.xlsx',
            'file_hash' => hash('sha256', 'uji.xlsx'),
            'tanggal_acuan' => '2026-07-31',
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'pinjaman_pokok' => 1000000,
            'pinjaman_bunga' => 100000,
            'source_key' => hash('sha256', 'pinjaman-pengujian'),
        ]);
        $saldo = PumkSaldoAwal::create([
            'pinjaman_id' => $pinjaman->id,
            'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 250000,
            'bunga_masuk' => 25000,
            'denda' => 0,
            'batch_id' => $batch->id,
        ]);
        $angsuran = PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-01-01',
            'pokok' => 100000,
            'bunga' => 10000,
            'denda' => 5000,
            'batch_id' => $batch->id,
        ]);

        $this->assertSame('1100000.00', $pinjaman->fresh()->total_pinjaman);
        $this->assertSame('115000.00', $angsuran->fresh()->total);
        $this->assertTrue($pinjaman->saldoAwal->is($saldo));
        $this->assertTrue($pinjaman->angsuran->first()->is($angsuran));
        $this->assertTrue($mitra->pinjaman->first()->is($pinjaman));
    }
}
