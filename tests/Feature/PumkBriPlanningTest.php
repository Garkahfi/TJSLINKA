<?php

namespace Tests\Feature;

use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\User;
use App\Services\Monitoring\PumkDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumkBriPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_pumk_admin_can_upsert_rka_and_distinguish_zero_from_missing_month(): void
    {
        $admin = User::factory()->create([
            'role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->actingAs($admin, 'pumk');

        $this->post(route('pumk-admin.bri-planning.year.store'), [
            'tahun_baru' => 2026,
        ])->assertRedirect(route('pumk-admin.bri-planning.index', ['tahun' => 2026]));

        $this->post(route('pumk-admin.bri-planning.rka.store'), [
            'tahun' => 2026, 'nominal_rka' => '175.000.000',
        ])->assertRedirect(route('pumk-admin.bri-planning.index', ['tahun' => 2026]));
        $this->post(route('pumk-admin.bri-planning.rka.store'), [
            'tahun' => 2026, 'nominal_rka' => '200.000.000',
        ])->assertRedirect();
        $this->post(route('pumk-admin.bri-planning.monthly.store'), [
            'tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => '0',
        ])->assertRedirect();

        $this->assertSame(1, PumkBriRkaTahunan::count());
        $this->assertDatabaseHas('pumk_bri_rka_tahunan', ['tahun' => 2026, 'nominal_rka' => 200000000]);
        $this->assertDatabaseHas('pumk_bri_penyaluran_bulanan', ['tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => 0]);
        $this->assertDatabaseMissing('pumk_bri_penyaluran_bulanan', ['tahun' => 2026, 'bulan' => 2]);

        $this->get(route('pumk-admin.bri-planning.index', ['tahun' => 2026]))
            ->assertOk()->assertSee('RKA &amp; Realisasi PUMK BRI', false)->assertSee('200.000.000')
            ->assertSee('bri-month-grid', false)->assertSee('Kosongkan')
            ->assertSee('data-confirm-empty=', false);
    }

    public function test_monthly_record_can_be_returned_to_not_entered_state(): void
    {
        $admin = User::factory()->create([
            'role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $entry = PumkBriPenyaluranBulanan::create(['tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => 10]);

        $this->actingAs($admin, 'pumk')
            ->delete(route('pumk-admin.bri-planning.monthly.destroy', $entry))
            ->assertRedirect(route('pumk-admin.bri-planning.index', ['tahun' => 2026]));

        $this->assertDatabaseMissing('pumk_bri_penyaluran_bulanan', ['id' => $entry->id]);
    }

    public function test_invalid_or_unregistered_year_cannot_bypass_add_year_flow(): void
    {
        $admin = User::factory()->create([
            'role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->actingAs($admin, 'pumk');

        $this->post(route('pumk-admin.bri-planning.year.store'), ['tahun_baru' => 2025])
            ->assertSessionHasErrors('tahun_baru');
        $this->post(route('pumk-admin.bri-planning.rka.store'), ['tahun' => 2029, 'nominal_rka' => 'Rp 10.000.000'])
            ->assertSessionHasErrors('tahun');
        $this->post(route('pumk-admin.bri-planning.monthly.store'), ['tahun' => 2029, 'bulan' => 1, 'nominal_penyaluran' => '0'])
            ->assertSessionHasErrors('tahun');
        $this->get(route('pumk-admin.bri-planning.index', ['tahun' => 2029]))->assertNotFound();
        $this->assertDatabaseMissing('pumk_bri_rka_tahunan', ['tahun' => 2029]);
        $this->assertDatabaseMissing('pumk_bri_penyaluran_bulanan', ['tahun' => 2029]);
    }

    public function test_admin_can_add_future_year_before_snapshot_and_years_remain_isolated(): void
    {
        $admin = User::factory()->create([
            'role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->actingAs($admin, 'pumk');

        $this->post(route('pumk-admin.bri-planning.year.store'), ['tahun_baru' => 2026])->assertRedirect();
        $this->post(route('pumk-admin.bri-planning.rka.store'), ['tahun' => 2026, 'nominal_rka' => '100.000.000'])->assertRedirect();
        $this->post(route('pumk-admin.bri-planning.monthly.store'), ['tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => '25.000.000'])->assertRedirect();

        $this->post(route('pumk-admin.bri-planning.year.store'), ['tahun_baru' => 2027])
            ->assertRedirect(route('pumk-admin.bri-planning.index', ['tahun' => 2027]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('pumk_bri_rka_tahunan', ['tahun' => 2027, 'nominal_rka' => null]);
        $this->assertDatabaseHas('pumk_activity_logs', ['action' => 'create_bri_year', 'module' => 'pumk_bri']);

        $this->get(route('pumk-admin.bri-planning.index', ['tahun' => 2027]))
            ->assertOk()->assertSee('RKA 2027')->assertSee('Belum tersedia')
            ->assertDontSee('Kosongkan')
            ->assertDontSee('25.000.000');

        $this->post(route('pumk-admin.bri-planning.rka.store'), ['tahun' => 2027, 'nominal_rka' => '200.000.000'])->assertRedirect();
        $this->post(route('pumk-admin.bri-planning.monthly.store'), ['tahun' => 2027, 'bulan' => 2, 'nominal_penyaluran' => '0'])->assertRedirect();
        $this->assertDatabaseHas('pumk_bri_penyaluran_bulanan', ['tahun' => 2027, 'bulan' => 2, 'nominal_penyaluran' => 0]);
        $this->assertDatabaseHas('pumk_bri_penyaluran_bulanan', ['tahun' => 2026, 'bulan' => 1, 'nominal_penyaluran' => 25000000]);
        $this->get(route('pumk-admin.bri-planning.index', ['tahun' => 2027]))
            ->assertOk()->assertSee('Input realisasi Februari 2027 akan dikosongkan.', false)
            ->assertSee('Kosongkan');

        $dashboard = app(PumkDashboardService::class)->bri(2027);
        $this->assertSame([2027, 2026], $dashboard['years']->all());
        $this->assertSame(200000000.0, $dashboard['ringkasan']['rka']);
        $this->assertSame(0.0, $dashboard['ringkasan']['realisasi']);
        $this->assertSame(0.0, $dashboard['ringkasan']['progres']);
        $this->assertFalse($dashboard['ketersediaan']['snapshot']);
        $this->assertSame(0, $dashboard['latest_month']);

        $this->post(route('pumk-admin.bri-planning.year.store'), ['tahun_baru' => 2027])
            ->assertSessionHasErrors('tahun_baru');
        $this->assertSame(2, PumkBriRkaTahunan::count());

        $superAdmin = User::factory()->create([
            'role' => 'super_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->actingAs($superAdmin, 'superadmin')
            ->get(route('superadmin.pumk.index', ['tab' => 'bri', 'tahun' => 2027]))
            ->assertOk()->assertSee('RKA 2027')->assertSee('Februari 2027')->assertSee('Rp 0')
            ->assertDontSee('+ Tambah Tahun')->assertDontSee('Simpan RKA')->assertDontSee('Kosongkan');
    }
}
