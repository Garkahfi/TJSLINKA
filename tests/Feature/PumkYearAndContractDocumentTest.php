<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PumkYearAndContractDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_year_selection_and_exports_use_only_the_selected_year(): void
    {
        $this->actingAs($this->user('pumk_admin'), 'pumk');
        [$mitra, $pinjaman] = $this->loan();
        PumkAngsuran::create(['pinjaman_id' => $pinjaman->id, 'periode' => '2025-12-01', 'nomor_bukti' => 'BUKTI-TAHUN-LAMA', 'pokok' => 100000, 'bunga' => 10000]);
        PumkAngsuran::create(['pinjaman_id' => $pinjaman->id, 'periode' => '2026-01-01', 'nomor_bukti' => 'BUKTI-TAHUN-BARU', 'pokok' => 50000, 'bunga' => 5000]);

        $this->get(route('pumk-admin.mitra.show', [$mitra, 'tahun' => '2025']))
            ->assertOk()->assertSee('Menampilkan periode: Tahun 2025')
            ->assertSee('BUKTI-TAHUN-LAMA')->assertDontSee('BUKTI-TAHUN-BARU')
            ->assertDontSee('Histori: Belum Dilengkapi')
            ->assertDontSee('Lengkapi Histori Tahun');
        $this->get(route('pumk-admin.mitra.show', $mitra))
            ->assertOk()->assertSee('Menampilkan periode: Tahun 2026');

        $excel = $this->get(route('pumk-admin.mitra.kartu.excel', [$mitra, $pinjaman, 'tahun' => '2025']));
        $excel->assertOk();
        $this->assertStringContainsString('2025.xls', (string) $excel->headers->get('content-disposition'));
        $this->assertStringContainsString('BUKTI-TAHUN-LAMA', $excel->getContent());
        $this->assertStringNotContainsString('BUKTI-TAHUN-BARU', $excel->getContent());
        $allYears = $this->get(route('pumk-admin.mitra.kartu.excel', [$mitra, $pinjaman, 'tahun' => 'semua']));
        $allYears->assertOk();
        $this->assertStringContainsString('semua-tahun.xls', (string) $allYears->headers->get('content-disposition'));
        $this->assertStringContainsString('BUKTI-TAHUN-LAMA', $allYears->getContent());
        $this->assertStringContainsString('BUKTI-TAHUN-BARU', $allYears->getContent());
        $pdf = $this->get(route('pumk-admin.mitra.kartu.pdf', [$mitra, $pinjaman, 'tahun' => '2025']));
        $pdf->assertOk();
        $this->assertStringContainsString('2025.pdf', (string) $pdf->headers->get('content-disposition'));
    }

    public function test_admin_can_replace_and_delete_private_contract_document_and_superadmin_can_only_read(): void
    {
        Storage::fake('local');
        [$mitra, $pinjaman] = $this->loan();
        $admin = $this->user('pumk_admin');
        $this->actingAs($admin, 'pumk');

        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => $mitra->nama_mitra,
            'spj_awal' => 'SPJ/TEST/001',
            'dokumen_spj_awal' => UploadedFile::fake()->create('spj-awal.pdf', 12, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $document = PumkPinjamanDokumen::firstOrFail();
        $oldPath = $document->file_path;
        Storage::disk('local')->assertExists($oldPath);
        $this->get(route('pumk-admin.mitra.dokumen.view', [$mitra, $pinjaman, $document]))->assertOk();

        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => $mitra->nama_mitra,
            'spj_awal' => 'SPJ/TEST/001',
            'dokumen_spj_awal' => UploadedFile::fake()->create('spj-revisi.pdf', 12, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $document->refresh();
        $this->assertNotSame($oldPath, $document->file_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->file_path);
        $this->get(route('pumk-admin.mitra.show', $mitra))
            ->assertOk()->assertSee('spj-revisi.pdf')->assertSee('Hapus dokumen kontrak ini?', false);
        $this->get(route('pumk-admin.mitra.edit', $mitra))
            ->assertOk()->assertSee('Dokumen lama pada jenis yang dipilih akan diganti. Lanjutkan?', false);

        $super = $this->user('super_admin');
        auth('pumk')->logout();
        $this->actingAs($super, 'superadmin');
        $this->get(route('superadmin.pumk.dokumen.view', [$mitra, $pinjaman, $document]))->assertOk();
        $this->get(route('superadmin.pumk.dokumen.download', [$mitra, $pinjaman, $document]))->assertOk();
        $this->delete(route('pumk-admin.mitra.dokumen.destroy', [$mitra, $pinjaman, $document]))
            ->assertRedirect(route('pumk-admin.login'));
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($admin, 'pumk');
        $path = $document->file_path;
        $this->delete(route('pumk-admin.mitra.dokumen.destroy', [$mitra, $pinjaman, $document]))->assertRedirect();
        $this->assertDatabaseCount('pumk_pinjaman_dokumen', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_document_requires_its_contract_number_and_cannot_be_read_from_another_loan(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('pumk_admin'), 'pumk');
        [$mitra, $pinjaman] = $this->loan();
        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => $mitra->nama_mitra,
            'dokumen_reschedule_1' => UploadedFile::fake()->create('reschedule.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('dokumen_reschedule_1');
        $this->assertDatabaseCount('pumk_pinjaman_dokumen', 0);

        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => $mitra->nama_mitra,
            'spj_awal' => 'SPJ/001',
            'dokumen_spj_awal' => UploadedFile::fake()->create('spj.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $document = PumkPinjamanDokumen::firstOrFail();
        $this->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => $mitra->nama_mitra,
            'spj_awal' => 'SPJ/001',
            'reschedule_ke1' => 'RS/001',
            'dokumen_reschedule_1' => UploadedFile::fake()->create('reschedule.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pumk_pinjaman_dokumen', [
            'pinjaman_id' => $pinjaman->id, 'jenis_dokumen' => 'reschedule_1',
        ]);
        $other = PumkPinjaman::create(['mitra_id' => $mitra->id, 'spj_awal' => 'SPJ/002', 'source_key' => hash('sha256', 'pinjaman-lain')]);
        $this->get(route('pumk-admin.mitra.dokumen.view', [$mitra, $other, $document]))->assertNotFound();
    }

    /** @return array{PumkMitra, PumkPinjaman} */
    private function loan(): array
    {
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Dokumen Uji', 'is_active' => true, 'source_key' => hash('sha256', 'mitra-dokumen-uji')]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'is_active' => true,
            'source_key' => hash('sha256', 'pinjaman-dokumen-uji'),
            'mulai_angsuran' => '2025-12-01', 'selesai_angsuran' => '2026-02-01',
            'pinjaman_pokok' => 1000000, 'pinjaman_bunga' => 100000,
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
