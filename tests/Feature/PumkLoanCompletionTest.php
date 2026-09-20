<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumkLoanCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_loan_with_remaining_balance_cannot_be_marked_paid(): void
    {
        [$admin, $mitra, $pinjaman] = $this->loan(100_000, 10_000);

        $this->actingAs($admin, 'pumk')
            ->from(route('pumk-admin.mitra.show', $mitra))
            ->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $pinjaman]))
            ->assertRedirect(route('pumk-admin.mitra.show', $mitra))
            ->assertSessionHasErrors('lunas');

        $this->assertSame(PumkPinjaman::STATUS_AKTIF, $pinjaman->fresh()->status);
        $this->assertTrue($pinjaman->fresh()->is_active);
    }

    public function test_paid_loan_is_archived_without_deleting_history_and_remains_downloadable(): void
    {
        [$admin, $mitra, $pinjaman] = $this->loan(100_000, 10_000);
        PumkAngsuran::create([
            'pinjaman_id' => $pinjaman->id,
            'periode' => '2026-01-01',
            'nomor_bukti' => 'BKM/LUNAS/001',
            'pokok' => 100_000,
            'bunga' => 10_000,
            'denda' => 0,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'pumk')
            ->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $pinjaman]), ['lunas_note' => 'Pelunasan pengujian'])
            ->assertRedirect(route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman->id]));

        $pinjaman->refresh();
        $this->assertSame(PumkPinjaman::STATUS_LUNAS, $pinjaman->status);
        $this->assertFalse($pinjaman->is_active);
        $this->assertNotNull($pinjaman->lunas_at);
        $this->assertSame($admin->id, $pinjaman->lunas_by);
        $this->assertFalse($mitra->fresh()->is_active);
        $this->assertDatabaseHas('pumk_angsuran', ['nomor_bukti' => 'BKM/LUNAS/001']);
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'mark_loan_paid']);
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'archive_partner']);

        $this->get(route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman->id]))
            ->assertOk()->assertSee('Pinjaman lunas')->assertSee('BKM/LUNAS/001')
            ->assertDontSee('Tambah Angsuran')
            ->assertDontSee('<button type="button" class="receivable-edit-button"', false);

        $this->get(route('pumk-admin.mitra.kartu.excel', [$mitra, $pinjaman]))->assertOk();
        $this->get(route('pumk-admin.mitra.kartu.pdf', [$mitra, $pinjaman]))->assertOk();
    }

    public function test_partner_stays_active_until_every_loan_is_paid_and_super_admin_can_monitor_archive(): void
    {
        [$admin, $mitra, $first] = $this->loan(0, 0);
        $second = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'completion-loan-two'),
            'pinjaman_pokok' => 0,
            'pinjaman_bunga' => 0,
            'status' => PumkPinjaman::STATUS_AKTIF,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'pumk')->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $first]))->assertRedirect();
        $this->assertTrue($mitra->fresh()->is_active);
        $this->actingAs($admin, 'pumk')->post(route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $second]))->assertRedirect();
        $this->assertFalse($mitra->fresh()->is_active);

        $super = User::factory()->create([
            'role' => 'super_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->actingAs($super, 'superadmin')
            ->get(route('superadmin.pumk.mitra', ['status' => 'lunas']))
            ->assertOk()->assertSee('Mitra Selesai')->assertSee('Selesai / Arsip');
        $this->get(route('superadmin.pumk.kartu', [$mitra, 'pinjaman' => $second->id]))
            ->assertOk()->assertSee('Lunas / Arsip')->assertDontSee('Tambah Angsuran');
        $this->get(route('superadmin.pumk.kartu.excel', [$mitra, $second]))->assertOk();

        $this->actingAs($admin, 'pumk')->put(route('pumk-admin.mitra.update', $mitra), [
            'nama_mitra' => 'Mitra Selesai',
            'tanggal_pencairan' => '2027-01-10',
            'pinjaman_pokok' => 50_000,
            'pinjaman_bunga' => 5_000,
        ])->assertRedirect(route('pumk-admin.mitra.show', $mitra));

        $this->assertTrue($mitra->fresh()->is_active);
        $this->assertSame(3, $mitra->pinjaman()->count());
        $this->assertSame(2, $mitra->pinjaman()->where('status', PumkPinjaman::STATUS_LUNAS)->count());
        $this->assertSame(1, $mitra->pinjamanAktif()->count());
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'reactivate_partner']);
    }

    /** @return array{User, PumkMitra, PumkPinjaman} */
    private function loan(float $principal, float $interest): array
    {
        $admin = User::factory()->create([
            'role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Selesai',
            'source_key' => hash('sha256', 'completion-partner-'.uniqid()),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $pinjaman = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => hash('sha256', 'completion-loan-'.uniqid()),
            'pinjaman_pokok' => $principal,
            'pinjaman_bunga' => $interest,
            'mulai_angsuran' => '2026-01-01',
            'selesai_angsuran' => '2026-01-01',
            'status' => PumkPinjaman::STATUS_AKTIF,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return [$admin, $mitra, $pinjaman];
    }
}
