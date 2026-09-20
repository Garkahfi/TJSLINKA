<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkImportBatch;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Models\User;
use App\Services\Pumk\PiutangCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PumkMitraManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_pumk_login(): void
    {
        $this->get(route('pumk-admin.mitra.index'))
            ->assertRedirect(route('pumk-admin.login'));
    }

    public function test_list_supports_server_side_filters_without_exposing_pii(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        [$wilayah, $sektor] = $this->references();
        $matching = $this->mitra('Mitra Filter Sesuai', $wilayah, $sektor, '081200001111');
        $this->loan($matching, 'lancar', 700000);

        $otherRegion = PumkWilayah::create(['nama' => 'Wilayah Lain', 'slug' => 'wilayah-lain']);
        $other = $this->mitra('Mitra Tidak Sesuai', $otherRegion, $sektor, '081299999999');
        $this->loan($other, 'macet', 900000);

        $this->get(route('pumk-admin.mitra.index', [
            'q' => 'Filter Sesuai',
            'wilayah' => $wilayah->id,
            'sektor' => $sektor->id,
            'kolektibilitas' => 'lancar',
        ]))
            ->assertOk()
            ->assertSee('Mitra Filter Sesuai')
            ->assertDontSee('Mitra Tidak Sesuai')
            ->assertDontSee('081200001111')
            ->assertDontSee('081299999999');
    }

    public function test_pumk_admin_can_create_a_mitra_and_pii_is_encrypted(): void
    {
        $admin = $this->pumkAdmin();
        $this->actingAs($admin, 'pumk');
        [$wilayah, $sektor] = $this->references();

        $response = $this->post(route('pumk-admin.mitra.store'), [
            'nama_mitra' => 'Mitra Baru PUMK',
            'jenis_usaha' => 'Perdagangan eceran',
            'sektor_usaha_id' => $sektor->id,
            'wilayah_id' => $wilayah->id,
            'alamat' => 'Alamat pengujian',
            'nama_pemilik' => 'Pemilik Pengujian',
            'no_ktp' => '1234567890123456',
            'no_telepon' => '081234567890',
            'no_rekening' => '9876543210',
            'is_active' => '1',
            'spj_awal' => 'SPJ/UJI/001',
            'reschedule_ke1' => 'SPJ/RS/UJI/001',
            'jenis_jaminan' => 'BPKB Kendaraan',
            'jaminan_no_pol' => 'AE 1234 XX',
            'jaminan_no_bpkb' => 'BPKB-UJI-001',
            'tanggal_pencairan' => '2026-01-10',
            'mulai_angsuran' => '2026-02-01',
            'selesai_angsuran' => '2027-01-01',
            'pinjaman_pokok' => 12000000,
            'persen_bunga' => 3,
            'pinjaman_bunga' => 360000,
            'nilai_angsuran_bulanan' => 1030000,
        ]);

        $mitra = PumkMitra::where('nama_mitra', 'Mitra Baru PUMK')->firstOrFail();
        $response->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $this->assertSame($admin->id, $mitra->created_by);
        $this->assertSame('1234567890123456', $mitra->no_ktp_encrypted);
        $this->assertDatabaseHas('pumk_pinjaman', [
            'mitra_id' => $mitra->id,
            'spj_awal' => 'SPJ/UJI/001',
            'reschedule_ke1' => 'SPJ/RS/UJI/001',
            'jenis_jaminan' => 'BPKB Kendaraan',
            'jaminan_no_pol' => 'AE 1234 XX',
            'jaminan_no_bpkb' => 'BPKB-UJI-001',
            'created_by' => $admin->id,
        ]);

        $raw = DB::table('pumk_mitra')->where('id', $mitra->id)->first();
        $this->assertNotSame('1234567890123456', $raw->no_ktp_encrypted);
        $this->assertNotSame('081234567890', $raw->no_telepon_encrypted);
        $this->assertNotSame('9876543210', $raw->no_rekening_encrypted);
    }

    public function test_pumk_admin_can_update_mitra_and_loan_source_data(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Nama Lama', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, 'lancar', 1000000);

        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => 'Nama Diperbarui',
            'sektor_usaha_id' => $sektor->id,
            'wilayah_id' => $wilayah->id,
            'is_active' => '1',
            'spj_awal' => 'SPJ-BARU',
            'pinjaman_pokok' => 1500000,
            'pinjaman_bunga' => 150000,
            'nilai_angsuran_bulanan' => 150000,
        ])
            ->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $this->assertSame('Nama Diperbarui', $mitra->fresh()->nama_mitra);
        $this->assertSame('SPJ-BARU', $pinjaman->fresh()->spj_awal);
        $this->assertSame('1650000.00', $pinjaman->fresh()->total_pinjaman);
    }

    public function test_adding_an_installment_recalculates_the_card_and_duplicate_period_is_rejected(): void
    {
        $admin = $this->pumkAdmin();
        $this->actingAs($admin, 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Mitra Angsuran', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, null, null, [
            'pinjaman_pokok' => 1000000,
            'pinjaman_bunga' => 100000,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-04-01',
            'nilai_angsuran_bulanan' => 100000,
        ]);

        $payload = ['periode' => '2026-01', 'nomor_bukti' => 'BKM/UJI/001', 'pokok' => 100000, 'bunga' => 10000, 'denda' => 5000];

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), $payload)
            ->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $this->assertDatabaseHas('pumk_angsuran', [
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-01-01 00:00:00',
            'nomor_bukti' => 'BKM/UJI/001',
            'created_by' => $admin->id,
        ]);
        $this->assertSame('900000.00', $pinjaman->fresh()->sisa_pokok);

        $card = $this->get(route('pumk-admin.mitra.show', $mitra));
        $card->assertOk()
            ->assertSee('Program')
            ->assertSee('Kemitraan dan Bina Lingkungan')
            ->assertSee('KARTU PIUTANG')
            ->assertSee('BKM/UJI/001')
            ->assertSee('Nomor Bukti')
            ->assertSee('Kekurangan');
        $this->assertSame(4, substr_count($card->getContent(), 'data-schedule-row'));

        $this->from(route('pumk-admin.mitra.show', $mitra))
            ->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), $payload)
            ->assertRedirect(route('pumk-admin.mitra.show', $mitra))
            ->assertSessionHasErrors('periode');

        $this->assertDatabaseCount('pumk_angsuran', 1);
    }

    public function test_formatted_rupiah_and_dash_are_normalized_and_manual_installment_can_be_edited(): void
    {
        $admin = $this->pumkAdmin();
        $this->actingAs($admin, 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Mitra Koreksi Angsuran', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, null, null, [
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-04-01',
            'nilai_angsuran_bulanan' => 250_000,
        ]);

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), [
            'periode' => '2026-01',
            'nomor_bukti' => 'BKM/FORMAT/001',
            'pokok' => '3.334.00',
            'bunga' => '-',
            'denda' => '-',
        ])->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $angsuran = PumkAngsuran::firstOrFail();
        $this->assertSame('333400.00', $angsuran->pokok);
        $this->assertSame('0.00', $angsuran->bunga);
        $this->assertSame('0.00', $angsuran->denda);
        $this->assertSame('333400.00', $angsuran->total);

        $card = $this->get(route('pumk-admin.mitra.show', $mitra));
        $card->assertOk()
            ->assertSee('data-edit-installment', false)
            ->assertSee('333.400')
            ->assertSee('Gunakan - jika tidak ada bunga');

        $this->patch(route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $angsuran]), [
            'periode' => '2026-02',
            'nomor_bukti' => 'BKM/FORMAT/002',
            'pokok' => '400.000',
            'bunga' => '10.000',
            'denda' => '-',
        ])->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $angsuran->refresh();
        $this->assertSame('2026-02-01', $angsuran->periode->toDateString());
        $this->assertSame('BKM/FORMAT/002', $angsuran->nomor_bukti);
        $this->assertSame('400000.00', $angsuran->pokok);
        $this->assertSame('10000.00', $angsuran->bunga);
        $this->assertSame('690000.00', $pinjaman->fresh()->total_sisa);
    }

    public function test_imported_installment_cannot_be_edited_from_the_manual_card(): void
    {
        $admin = $this->pumkAdmin();
        $this->actingAs($admin, 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Mitra Angsuran Impor', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, null, null, [
            'pinjaman_pokok' => 500_000,
            'pinjaman_bunga' => 50_000,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-02-01',
        ]);
        $batch = PumkImportBatch::create([
            'nama_file' => 'sumber.xlsx',
            'file_hash' => hash('sha256', 'batch-edit-protection'),
            'status' => 'completed',
        ]);
        $angsuran = PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-01-01',
            'pokok' => 100_000,
            'bunga' => 10_000,
            'batch_id' => $batch->id,
        ]);

        $this->get(route('pumk-admin.mitra.show', $mitra))
            ->assertOk()
            ->assertDontSee('<button type="button" class="receivable-edit-button"', false);

        $this->patch(route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $angsuran]), [
            'periode' => '2026-01',
            'pokok' => '1',
            'bunga' => '-',
            'denda' => '-',
        ])->assertForbidden();

        $this->assertSame('100000.00', $angsuran->fresh()->pokok);
    }

    public function test_historical_installment_requires_confirmation_and_replaces_opening_balance_safely(): void
    {
        $admin = $this->pumkAdmin();
        $this->actingAs($admin, 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Mitra Konflik Saldo Awal', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, 'lancar', 800_000, [
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'sisa_pokok' => 800_000,
            'sisa_bunga' => 80_000,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-04-01',
            'nilai_angsuran_bulanan' => 250_000,
            'source_updated_at' => '2026-07-30 23:59:59',
        ]);
        PumkSaldoAwal::create([
            'pinjaman_id' => $pinjaman->id,
            'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 200_000,
            'bunga_masuk' => 20_000,
            'denda' => 0,
        ]);
        app(PiutangCalculator::class)->simpanBaselineSumber($pinjaman);

        $payload = [
            'periode' => '2015-06',
            'nomor_bukti' => 'BKM/HISTORI/2015',
            'pokok' => '100.000',
            'bunga' => '10.000',
            'denda' => '-',
        ];

        $this->from(route('pumk-admin.mitra.show', $mitra))
            ->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), $payload)
            ->assertRedirect(route('pumk-admin.mitra.show', $mitra))
            ->assertSessionHasErrors('periode')
            ->assertSessionHas('saldo_awal_overlap');

        $this->assertDatabaseCount('pumk_angsuran', 0);
        $this->assertDatabaseHas('pumk_saldo_awal', ['pinjaman_id' => $pinjaman->id]);
        $this->get(route('pumk-admin.mitra.show', $mitra))
            ->assertOk()
            ->assertSee('Ya, Hapus Saldo Awal &amp; Simpan', false)
            ->assertSee('pembayaran dihitung dua kali');

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), $payload + [
            'hapus_saldo_awal' => '1',
        ])->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $this->assertDatabaseMissing('pumk_saldo_awal', ['pinjaman_id' => $pinjaman->id]);
        $this->assertDatabaseHas('pumk_angsuran', [
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2015-06-01 00:00:00',
            'nomor_bukti' => 'BKM/HISTORI/2015',
        ]);
        $this->assertSame('900000.00', $pinjaman->fresh()->sisa_pokok);
        $this->assertSame('90000.00', $pinjaman->fresh()->sisa_bunga);
        $this->assertSame('0.00', (string) $pinjaman->fresh()->baseline_sumber['total_pokok_masuk']);
    }

    public function test_historical_installment_without_opening_balance_is_stored_immediately(): void
    {
        $admin = $this->pumkAdmin();
        $this->actingAs($admin, 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Mitra Histori Tanpa Saldo Awal', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, null, null, [
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-04-01',
            'nilai_angsuran_bulanan' => 250_000,
        ]);

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), [
            'periode' => '2009-03',
            'nomor_bukti' => 'BKM/HISTORI/2009',
            'pokok' => '75.000',
            'bunga' => '-',
            'denda' => '-',
        ])->assertRedirect(route('pumk-admin.mitra.show', $mitra))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pumk_angsuran', [
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2009-03-01 00:00:00',
            'pokok' => 75_000,
        ]);
        $this->assertDatabaseCount('pumk_saldo_awal', 0);
    }

    public function test_card_can_be_downloaded_as_excel_and_pdf(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        [$wilayah, $sektor] = $this->references();
        $mitra = $this->mitra('Mitra Ekspor', $wilayah, $sektor);
        $pinjaman = $this->loan($mitra, 'lancar', 990_000, [
            'pinjaman_pokok' => 1_000_000,
            'pinjaman_bunga' => 100_000,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-03-01',
            'nilai_angsuran_bulanan' => 366_667,
        ]);

        $excel = $this->get(route('pumk-admin.mitra.kartu.excel', [$mitra, $pinjaman]));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('<?mso-application progid="Excel.Sheet"?>', $excel->getContent());
        $this->assertStringContainsString('KARTU PIUTANG', $excel->getContent());

        $pdf = $this->get(route('pumk-admin.mitra.kartu.pdf', [$mitra, $pinjaman]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('kartu-piutang-mitra-ekspor-2026.pdf', (string) $pdf->headers->get('content-disposition'));
    }

    private function pumkAdmin(): User
    {
        return User::factory()->create([
            'role' => 'pumk_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /** @return array{0: PumkWilayah, 1: PumkSektorUsaha} */
    private function references(): array
    {
        return [
            PumkWilayah::firstOrCreate(['slug' => 'wilayah-uji'], ['nama' => 'Wilayah Uji']),
            PumkSektorUsaha::firstOrCreate(['slug' => 'perdagangan-uji'], ['nama' => 'Perdagangan Uji']),
        ];
    }

    private function mitra(
        string $name,
        PumkWilayah $wilayah,
        PumkSektorUsaha $sektor,
        string $phone = '081200000000',
    ): PumkMitra {
        return PumkMitra::create([
            'nama_mitra' => $name,
            'wilayah_id' => $wilayah->id,
            'sektor_usaha_id' => $sektor->id,
            'no_telepon_encrypted' => $phone,
            'source_key' => hash('sha256', $name),
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function loan(
        PumkMitra $mitra,
        ?string $collectibility,
        int|float|null $remaining,
        array $attributes = [],
    ): PumkPinjaman {
        return PumkPinjaman::create($attributes + [
            'mitra_id' => $mitra->id,
            'kolektibilitas' => $collectibility,
            'total_sisa' => $remaining,
            'source_key' => hash('sha256', 'loan-'.$mitra->id),
            'is_active' => true,
        ]);
    }
}
