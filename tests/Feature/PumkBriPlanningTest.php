<?php

namespace Tests\Feature;

use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\User;
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
            ->assertOk()->assertSee('RKA &amp; Realisasi PUMK BRI', false)->assertSee('200.000.000');
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
}
