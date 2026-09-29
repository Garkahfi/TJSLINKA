<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use App\Services\Monitoring\PumkClassificationService;
use App\Services\Monitoring\PumkInternalMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PumkMonitoringDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_historical_classification_is_selected_per_attribute_and_unrelated_edit_does_not_erase_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create(['nama_mitra' => 'Mitra Kategori', 'source_key' => hash('sha256', 'kategori-mitra'), 'sektor_sumber' => 'Perdagangan', 'wilayah_sumber' => 'Madiun']);
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'kategori-pinjaman'), 'tanggal_pencairan' => '2025-01-01',
            'pinjaman_pokok' => 1_000_000, 'pinjaman_bunga' => 0,
        ]);
        $classifications = app(PumkClassificationService::class);
        $classifications->record($mitra, null, 'sektor', 'Pertanian', CarbonImmutable::parse('2025-01-01', 'Asia/Jakarta'), 'verified_correction', 'fixture-2025');
        $classifications->record($mitra, null, 'sektor', 'Perdagangan', CarbonImmutable::parse('2026-01-01', 'Asia/Jakarta'), 'verified_correction', 'fixture-2026');
        $classifications->record($mitra, null, 'wilayah', 'Madiun', CarbonImmutable::parse('2025-01-01', 'Asia/Jakarta'), 'verified_correction', 'fixture-region');
        $classifications->record(null, $loan, 'kolektibilitas', null, CarbonImmutable::parse('2025-01-01', 'Asia/Jakarta'), 'import', 'fixture-quality');
        $mitra->update(['jenis_usaha' => 'Catatan administrasi saja']);

        $monitoring = app(PumkInternalMonitoringService::class);
        $old = $monitoring->report(2025);
        $current = $monitoring->report(2026);

        $this->assertSame('Pertanian', $old['sektor']->first()['label']);
        $this->assertSame('Perdagangan', $current['sektor']->first()['label']);
        $this->assertSame('Belum Dinilai', $old['kolektibilitas']->first()['label']);
        $this->assertSame('Jawa Timur', $old['sebaran_provinsi']->first()['label']);
        $this->assertDatabaseCount('pumk_classification_history', 4);
        $classifications->record($mitra, null, 'sektor', 'Pertanian', CarbonImmutable::parse('2025-01-01', 'Asia/Jakarta'), 'verified_correction', 'fixture-2025');
        $this->assertDatabaseCount('pumk_classification_history', 4);
    }

    public function test_diagnostics_is_role_limited_and_explains_unknown_balance_without_exposing_identity_numbers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Jakarta'));
        $mitra = PumkMitra::create([
            'nama_mitra' => 'Mitra Diagnostik', 'source_key' => hash('sha256', 'diagnostik-mitra'),
            'no_ktp_encrypted' => '1234567890123456',
        ]);
        PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => hash('sha256', 'diagnostik-pinjaman'), 'tanggal_pencairan' => '2026-01-01',
            'pinjaman_pokok' => 100_000, 'pinjaman_bunga' => null,
        ]);

        $this->get(route('pumk-admin.monitoring.diagnostics', ['year' => 2026]))->assertRedirect();
        $ordinary = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($ordinary, 'web')->get(route('pumk-admin.monitoring.diagnostics', ['year' => 2026]))->assertForbidden();

        $pumk = User::factory()->create(['role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($pumk, 'pumk')->get(route('pumk-admin.monitoring.diagnostics', ['year' => 2026]))
            ->assertOk()->assertSee('missing_interest')->assertSee('Mitra Diagnostik')
            ->assertDontSee('1234567890123456');
        $this->get(route('pumk-admin.monitoring.diagnostics', ['year' => 2024]))->assertSessionHasErrors('year');

        $super = User::factory()->create(['role' => 'super_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($super, 'superadmin')->get(route('superadmin.pumk.diagnostics', ['year' => 2026]))
            ->assertOk()->assertSee('missing_interest')->assertDontSee('1234567890123456');
    }

    public function test_internal_year_options_are_bounded_and_service_rejects_out_of_range_years(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Jakarta'));
        $monitoring = app(PumkInternalMonitoringService::class);
        $this->assertSame([2026, 2025], $monitoring->report()['years']);
        $this->assertSame('unavailable', $monitoring->report(2025)['status']);
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($user, 'web')->get(route('monitoring.inka', ['year' => 2024]))->assertSessionHasErrors('year');
        $this->get(route('monitoring.inka', ['year' => 2027]))->assertSessionHasErrors('year');
        $this->get(route('monitoring.inka', ['year' => 'abc']))->assertSessionHasErrors('year');
        $this->expectException(\InvalidArgumentException::class);
        $monitoring->report(2024);
    }
}
