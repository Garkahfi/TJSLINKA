<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkImportBatch;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PumkPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_view_download_replace_and_delete_optional_payment_proof(): void
    {
        Storage::fake('local');
        $admin = $this->user('pumk_admin');
        $this->actingAs($admin, 'pumk');
        [$mitra, $pinjaman] = $this->loan();

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), [
            'periode' => '2026-01', 'nomor_bukti' => 'BKM/2026/001',
            'pokok' => '100000', 'bunga' => '10000', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->create('bukti-januari.pdf', 20, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $angsuran = PumkAngsuran::firstOrFail();
        $this->assertSame('BKM/2026/001', $angsuran->nomor_bukti);
        $this->assertSame('bukti-januari.pdf', $angsuran->bukti_pembayaran_nama_asli);
        Storage::disk('local')->assertExists($angsuran->bukti_pembayaran_path);
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'upload_payment_proof', 'entity_id' => $angsuran->id]);

        $this->get(route('pumk-admin.mitra.show', [$mitra, 'tahun' => 2026]))
            ->assertOk()->assertSee('Nomor &amp; Bukti Pembayaran', false)
            ->assertSee('BKM/2026/001')->assertSee('Lihat Bukti')->assertSee('Ganti Bukti');
        $this->get(route('pumk-admin.mitra.angsuran.bukti.view', [$mitra, $pinjaman, $angsuran]))->assertOk();
        $this->get(route('pumk-admin.mitra.angsuran.bukti.download', [$mitra, $pinjaman, $angsuran]))->assertOk();
        [$otherMitra, $otherLoan] = $this->loan('lain');
        $this->get(route('pumk-admin.mitra.angsuran.bukti.view', [$otherMitra, $otherLoan, $angsuran]))->assertNotFound();

        $oldPath = $angsuran->bukti_pembayaran_path;
        $this->patch(route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $angsuran]), [
            'periode' => '2026-01', 'nomor_bukti' => 'BKM/2026/001-R',
            'pokok' => '100000', 'bunga' => '10000', 'denda' => '-',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $angsuran->fresh()->bukti_pembayaran_path);

        $this->patch(route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $angsuran]), [
            'periode' => '2026-01', 'nomor_bukti' => 'BKM/2026/001-R',
            'pokok' => '100000', 'bunga' => '10000', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->image('bukti-revisi.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $angsuran->refresh();
        $this->assertSame('bukti-revisi.jpg', $angsuran->bukti_pembayaran_nama_asli);
        $this->assertNotSame($oldPath, $angsuran->bukti_pembayaran_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($angsuran->bukti_pembayaran_path);
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'replace_payment_proof', 'entity_id' => $angsuran->id]);

        $replacementPath = $angsuran->bukti_pembayaran_path;
        $this->delete(route('pumk-admin.mitra.angsuran.bukti.destroy', [$mitra, $pinjaman, $angsuran]))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('pumk_angsuran', ['id' => $angsuran->id, 'nomor_bukti' => 'BKM/2026/001-R']);
        $this->assertNull($angsuran->fresh()->bukti_pembayaran_path);
        Storage::disk('local')->assertMissing($replacementPath);
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'delete_payment_proof', 'entity_id' => $angsuran->id]);
    }

    public function test_payment_proof_is_optional_and_invalid_or_oversized_files_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('pumk_admin'), 'pumk');
        [$mitra, $pinjaman] = $this->loan();

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]), [
            'periode' => '2026-01', 'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
        ])->assertSessionHasNoErrors();
        $this->assertNull(PumkAngsuran::firstOrFail()->bukti_pembayaran_path);

        [$mitraImage, $pinjamanImage] = $this->loan('gambar');
        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitraImage, $pinjamanImage]), [
            'periode' => '2026-01', 'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->image('bukti.png'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('bukti.png', PumkAngsuran::where('pinjaman_id', $pinjamanImage->id)->firstOrFail()->bukti_pembayaran_nama_asli);

        [$mitra2, $pinjaman2] = $this->loan('invalid');
        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra2, $pinjaman2]), [
            'periode' => '2026-01', 'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->create('bukti.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('bukti_pembayaran');
        $this->assertDatabaseMissing('pumk_angsuran', ['pinjaman_id' => $pinjaman2->id]);

        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra2, $pinjaman2]), [
            'periode' => '2026-01', 'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->create('bukti.txt', 10, 'application/pdf'),
        ])->assertSessionHasErrors('bukti_pembayaran');
        $this->assertDatabaseMissing('pumk_angsuran', ['pinjaman_id' => $pinjaman2->id]);

        [$mitra3, $pinjaman3] = $this->loan('besar');
        $this->post(route('pumk-admin.mitra.angsuran.store', [$mitra3, $pinjaman3]), [
            'periode' => '2026-01', 'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->create('besar.pdf', config('pumk.payment_proof_max_kb') + 1, 'application/pdf'),
        ])->assertSessionHasErrors('bukti_pembayaran');
        $this->assertDatabaseMissing('pumk_angsuran', ['pinjaman_id' => $pinjaman3->id]);
    }

    public function test_historical_installment_confirmation_keeps_payment_proof_in_the_same_request(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('pumk_admin'), 'pumk');
        [$mitra, $pinjaman] = $this->loan('historical');
        PumkSaldoAwal::create([
            'pinjaman_id' => $pinjaman->id,
            'cutoff_date' => '2025-12-31',
            'pokok_masuk' => 1000,
            'bunga_masuk' => 0,
            'denda' => 0,
        ]);

        $payload = [
            'periode' => '2025-11', 'nomor_bukti' => 'BKM/2025/011',
            'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
        ];
        $route = route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]);
        $this->post($route, $payload + [
            'bukti_pembayaran' => UploadedFile::fake()->create('lama.pdf', 5, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasErrors('periode')
            ->assertSessionHas('saldo_awal_overlap.proof_was_uploaded', true);
        $this->get(route('pumk-admin.mitra.show', $mitra))
            ->assertOk()->assertSee('Pilih ulang bukti pembayaran');
        $this->assertDatabaseMissing('pumk_angsuran', ['pinjaman_id' => $pinjaman->id]);

        $this->post($route, $payload + [
            'hapus_saldo_awal' => '1',
            'bukti_pembayaran' => UploadedFile::fake()->create('lama.pdf', 5, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $angsuran = PumkAngsuran::firstOrFail();
        $this->assertSame('lama.pdf', $angsuran->bukti_pembayaran_nama_asli);
        Storage::disk('local')->assertExists($angsuran->bukti_pembayaran_path);
        $this->assertDatabaseMissing('pumk_saldo_awal', ['pinjaman_id' => $pinjaman->id]);
        $this->get(route('pumk-admin.mitra.show', [$mitra, 'tahun' => 2025]))
            ->assertOk()->assertSee('Lihat Bukti')->assertSee('BKM/2025/011');
        $this->get(route('pumk-admin.mitra.show', [$mitra, 'tahun' => 2026]))
            ->assertOk()->assertDontSee('Lihat Bukti')->assertDontSee('BKM/2025/011');
    }

    public function test_superadmin_can_read_but_cannot_modify_proof_and_imported_payment_stays_read_only(): void
    {
        Storage::fake('local');
        $admin = $this->user('pumk_admin');
        [$mitra, $pinjaman] = $this->loan();
        $path = 'pumk/angsuran/1/bukti/manual.pdf';
        Storage::disk('local')->put($path, 'proof');
        $angsuran = PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2026-01-01',
            'pokok' => 1000, 'bunga' => 0, 'denda' => 0, 'created_by' => $admin->id,
            'bukti_pembayaran_path' => $path, 'bukti_pembayaran_nama_asli' => 'manual.pdf',
            'bukti_pembayaran_mime' => 'application/pdf', 'bukti_pembayaran_size' => 5,
            'bukti_pembayaran_uploaded_at' => now(),
        ]);

        $super = $this->user('super_admin');
        $this->actingAs($super, 'superadmin');
        $this->get(route('superadmin.pumk.kartu', [$mitra, 'tahun' => 2026]))->assertOk()->assertSee('Lihat Bukti');
        $this->get(route('superadmin.pumk.angsuran.bukti.view', [$mitra, $pinjaman, $angsuran]))->assertOk();
        $this->get(route('superadmin.pumk.angsuran.bukti.download', [$mitra, $pinjaman, $angsuran]))->assertOk();
        $this->delete(route('pumk-admin.mitra.angsuran.bukti.destroy', [$mitra, $pinjaman, $angsuran]))
            ->assertRedirect(route('pumk-admin.login'));
        Storage::disk('local')->assertExists($path);

        auth('superadmin')->logout();
        $this->actingAs($admin, 'pumk');
        $batch = PumkImportBatch::create(['nama_file' => 'source.xlsx', 'file_hash' => hash('sha256', 'source-proof'), 'status' => 'completed']);
        $imported = PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2026-02-01',
            'pokok' => 1000, 'bunga' => 0, 'denda' => 0, 'batch_id' => $batch->id,
        ]);
        $this->patch(route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $imported]), [
            'periode' => '2026-02', 'pokok' => '1000', 'bunga' => '-', 'denda' => '-',
            'bukti_pembayaran' => UploadedFile::fake()->image('tidak-boleh.png'),
        ])->assertForbidden();
        $this->assertNull($imported->fresh()->bukti_pembayaran_path);
    }

    public function test_proof_remains_after_loan_is_marked_paid(): void
    {
        Storage::fake('local');
        $admin = $this->user('pumk_admin');
        $this->actingAs($admin, 'pumk');
        [$mitra, $pinjaman] = $this->loan('lunas', 0, 0);
        $path = 'pumk/angsuran/paid/bukti.pdf';
        Storage::disk('local')->put($path, 'proof');
        $angsuran = PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id, 'periode' => '2026-01-01',
            'pokok' => 0, 'bunga' => 0, 'denda' => 0, 'created_by' => $admin->id,
            'bukti_pembayaran_path' => $path, 'bukti_pembayaran_nama_asli' => 'bukti.pdf',
            'bukti_pembayaran_mime' => 'application/pdf', 'bukti_pembayaran_size' => 5,
            'bukti_pembayaran_uploaded_at' => now(),
        ]);

        $this->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $pinjaman]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(PumkPinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
        $this->assertSame($path, $angsuran->fresh()->bukti_pembayaran_path);
        Storage::disk('local')->assertExists($path);
        $this->get(route('pumk-admin.mitra.angsuran.bukti.view', [$mitra, $pinjaman, $angsuran]))->assertOk();
    }

    /** @return array{PumkMitra, PumkPinjaman} */
    private function loan(string $suffix = 'utama', int $principal = 1_000_000, int $interest = 100_000): array
    {
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Bukti '.$suffix,
            'source_key' => hash('sha256', 'mitra-bukti-'.$suffix), 'is_active' => true,
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'pinjaman-bukti-'.$suffix),
            'mulai_angsuran' => '2026-01-01', 'selesai_angsuran' => '2026-12-01',
            'pinjaman_pokok' => $principal, 'pinjaman_bunga' => $interest,
            'status' => PumkPinjaman::STATUS_AKTIF, 'is_active' => true,
        ]);

        return [$mitra, $pinjaman];
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role, 'is_admin' => true, 'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
