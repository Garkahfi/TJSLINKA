<?php

namespace Tests\Feature;

use App\Models\PumkAngsuran;
use App\Models\PumkClassificationHistory;
use App\Models\PumkLoanClosure;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Models\User;
use App\Services\Pumk\PumkCollectibilitySummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PumkCollectibilitySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_signed_amounts_count_each_loan_once_across_active_and_archive(): void
    {
        $a = $this->mitra('Mitra A');
        $this->loan($a, 'lancar', '1000000.00');
        $this->loan($a, 'macet', '2000000.00');
        $b = $this->mitra('Mitra B', false);
        $this->loan($b, 'macet', '100000.00', true);
        $this->loan($this->mitra('Mitra C', false), 'lancar', '-193232.00', true);
        $this->loan($this->mitra('Mitra D'), 'kurang_lancar', '500000.00');
        $this->loan($this->mitra('Mitra E', false), 'diragukan', '-20000.00', true);
        $this->loan($this->mitra('Mitra F'), 'lancar', '0.00');
        $this->loan($this->mitra('Mitra G'), null, '250000.00');

        $result = app(PumkCollectibilitySummaryService::class)->summarize([]);

        $this->assertSame([
            'lancar' => '806768.00', 'kurang_lancar' => '500000.00',
            'diragukan' => '-20000.00', 'macet' => '2100000.00',
            'belum_dinilai' => '250000.00',
        ], $result['nominal']);
        $this->assertSame('3636768.00', $result['subtotal']);
        $this->assertSame(0, $result['unknown_balances']);

        $this->actingAs($this->admin(), 'pumk');
        $this->get(route('pumk-admin.mitra.index'))
            ->assertOk()->assertDontSee('class="pumk-collectibility-footer"', false)->assertDontSee('Rekap Nominal Kolektibilitas');
        $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => '']))
            ->assertOk()->assertDontSee('class="pumk-collectibility-footer"', false);
        foreach ([
            'lancar' => ['Total Sisa Lancar:', 'Rp806.768'],
            'kurang_lancar' => ['Total Sisa Kurang Lancar:', 'Rp500.000'],
            'diragukan' => ['Total Sisa Diragukan:', '-Rp20.000'],
            'macet' => ['Total Sisa Macet:', 'Rp2.100.000'],
        ] as $category => [$label, $amount]) {
            $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => $category]))
                ->assertOk()->assertSee('class="pumk-collectibility-footer"', false)->assertSee($label)->assertSee($amount)
                ->assertDontSee('Rekap Nominal Kolektibilitas')
                ->assertDontSee('Rp3.636.768');
        }
    }

    public function test_filters_apply_to_owners_but_status_collectibility_and_page_do_not_limit_summary(): void
    {
        $west = PumkWilayah::create(['nama' => 'Wilayah Barat', 'slug' => 'barat', 'is_active' => true]);
        $east = PumkWilayah::create(['nama' => 'Wilayah Timur', 'slug' => 'timur', 'is_active' => true]);
        $trade = PumkSektorUsaha::create(['nama' => 'Perdagangan', 'slug' => 'perdagangan', 'is_active' => true]);
        $service = PumkSektorUsaha::create(['nama' => 'Jasa', 'slug' => 'jasa', 'is_active' => true]);
        $target = $this->mitra('Mitra Pilihan');
        $target->update(['wilayah_id' => $west->id, 'sektor_usaha_id' => $trade->id]);
        $this->loan($target, 'macet', '125.00');
        $this->loan($target, 'macet', '-25.00', true);
        $other = $this->mitra('Mitra Pilihan Lain');
        $other->update(['wilayah_id' => $east->id, 'sektor_usaha_id' => $service->id]);
        $this->loan($other, 'lancar', '500.00');
        for ($i = 1; $i <= 16; $i++) {
            $this->loan($this->mitra('Mitra Nomor '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)), 'lancar', '10.00');
        }

        $summary = app(PumkCollectibilitySummaryService::class);
        $this->assertSame('100.00', $summary->summarize(['q' => 'Mitra Pilihan', 'wilayah' => $west->id, 'sektor' => $trade->id])['subtotal']);
        $this->assertSame('0.00', $summary->summarize(['wilayah' => $east->id, 'sektor' => $trade->id])['subtotal']);
        $this->assertSame('760.00', $summary->summarize([])['subtotal']);

        $this->actingAs($this->admin(), 'pumk');
        $this->get(route('pumk-admin.mitra.index', ['status' => 'aktif', 'page' => 1]))
            ->assertOk()->assertDontSee('class="pumk-collectibility-footer"', false);
        $this->get(route('pumk-admin.mitra.index', ['status' => 'aktif', 'kolektibilitas' => 'macet', 'page' => 1]))
            ->assertOk()->assertSee('Total Sisa Macet:')->assertSee('Rp100');
        $this->get(route('pumk-admin.mitra.index', ['status' => 'lunas', 'kolektibilitas' => 'macet', 'page' => 2]))
            ->assertOk()->assertSee('Total Sisa Macet:')->assertSee('Rp100');
        $this->get(route('pumk-admin.mitra.index', ['status' => 'semua', 'kolektibilitas' => 'lancar', 'page' => 1]))
            ->assertOk()->assertSee('Total Sisa Lancar:')->assertSee('Rp660');
        $this->get(route('pumk-admin.mitra.index', ['status' => 'semua', 'kolektibilitas' => 'lancar', 'page' => 2]))
            ->assertOk()->assertSee('Total Sisa Lancar:')->assertSee('Rp660')
            ->assertSeeInOrder(['Total Sisa Lancar:', 'pumk-pagination'])
            ->assertDontSee('Total Sisa Macet:');
        $this->get(route('pumk-admin.mitra.index', [
            'q' => 'Mitra Pilihan', 'wilayah' => $west->id, 'sektor' => $trade->id,
            'status' => 'aktif', 'kolektibilitas' => 'macet',
        ]))->assertOk()->assertSee('Total Sisa Macet:')->assertSee('Rp100');
        $this->get(route('pumk-admin.mitra.index', ['status' => 'semua']))
            ->assertOk()->assertDontSee('class="pumk-collectibility-footer"', false);
        $this->get(route('pumk-admin.mitra.index', ['q' => 'Tidak Ada Mitra Ini', 'kolektibilitas' => 'macet']))
            ->assertOk()->assertSee('Tidak ada mitra yang sesuai')->assertSee('Total Sisa Macet:')->assertSee('Rp0');
        $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'tidak_valid']))
            ->assertRedirect();
    }

    public function test_closure_metadata_conflicts_and_unproven_legacy_zero_are_unknown(): void
    {
        $conflict = $this->loan($this->mitra('Konflik', false), 'macet', '10.00', true);
        $conflict->closures()->firstOrFail()->update(['settlement_snapshot' => ['lunas_total_saldo' => '11.00']]);
        $zero = $this->loan($this->mitra('Nol Legacy', false), 'lancar', '0.00', true);
        $zero->closures()->delete();
        $zero->update(['lunas_total_saldo' => null]);
        $missingActive = $this->loan($this->mitra('Saldo Aktif Belum Diketahui'), null, '1000.00');
        DB::table('pumk_pinjaman')->where('id', $missingActive->id)->update([
            'total_sisa' => null, 'pinjaman_pokok' => null,
            'source_updated_at' => null, 'baseline_sumber' => null,
        ]);
        $unproven = $this->loan($this->mitra('Legacy Tidak Cocok', false), 'macet', '75.00', true);
        $unproven->closures()->delete();
        $unproven->update([
            'lunas_total_saldo' => null, 'pinjaman_pokok' => '100.00',
            'source_updated_at' => null, 'baseline_sumber' => null,
        ]);
        $retained = $this->loan($this->mitra('Retained', false), 'diragukan', '-100.00', true);
        $retained->closures()->delete();
        $retained->update([
            'lunas_total_saldo' => null, 'source_updated_at' => '2026-08-01 00:00:00',
            'baseline_sumber' => [
                'sisa_pokok' => '-100.00', 'sisa_bunga' => '0.00',
                'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00', 'total_denda_masuk' => '0.00',
                'bulan_tunggakan' => 0, 'nilai_tunggakan' => '0.00', 'kolektibilitas' => 'diragukan',
            ],
        ]);
        $closureOnly = $this->loan($this->mitra('Snapshot Only', false), 'kurang_lancar', '30.00', true);
        $closureOnly->update(['lunas_total_saldo' => null]);
        $reopened = $this->loan($this->mitra('Reopened'), 'lancar', '300.00');
        PumkLoanClosure::create([
            'pinjaman_id' => $reopened->id,
            'closed_at' => now()->subDays(2),
            'reopened_at' => now()->subDay(),
            'settlement_snapshot' => ['lunas_total_saldo' => '900.00'],
        ]);

        $result = app(PumkCollectibilitySummaryService::class)->summarize([]);

        $this->assertSame(4, $result['unknown_balances']);
        $this->assertSame('-100.00', $result['nominal']['diragukan']);
        $this->assertSame('30.00', $result['nominal']['kurang_lancar']);
        $this->assertSame('300.00', $result['nominal']['lancar']);
        $this->assertSame('230.00', $result['subtotal']);
        $selected = app(PumkCollectibilitySummaryService::class)->summarize([], 'macet');
        $this->assertSame(2, $selected['unknown_balances']);
        $this->assertSame('0.00', $selected['nominal']['macet']);
        $this->actingAs($this->admin(), 'pumk');
        $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'macet']))
            ->assertOk()->assertSee('Subtotal Sisa Macet:')->assertSee('Rp0')
            ->assertSee('2 pinjaman pada kategori ini memiliki saldo belum tersedia.')
            ->assertDontSee('4 pinjaman memiliki saldo belum tersedia');
        $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'diragukan']))
            ->assertOk()->assertSee('Total Sisa Diragukan:')->assertSee('-Rp100')
            ->assertDontSee('pinjaman pada kategori ini memiliki saldo belum tersedia.');
    }

    public function test_read_only_card_calculation_handles_stale_cache_without_changing_stored_collectibility(): void
    {
        $loan = $this->loan($this->mitra('Mitra Cache'), 'macet', '1000000.00');
        PumkAngsuran::create([
            'pinjaman_id' => $loan->id, 'periode' => '2026-09-01',
            'pokok' => '200000.00', 'bunga' => '0.00', 'denda' => '0.00',
        ]);
        // Simulate a stale imported/legacy cache without invoking the normal payment observer.
        DB::table('pumk_pinjaman')->where('id', $loan->id)->update([
            'total_sisa' => '1000000.00', 'kolektibilitas' => 'macet',
        ]);
        $before = $loan->fresh()->getAttributes();
        $service = app(PumkCollectibilitySummaryService::class);

        $this->assertSame('800000.00', $service->summarize([])['nominal']['macet']);
        $this->assertSame(1, $service->summarize([])['cache_differences']);
        $this->actingAs($this->admin(), 'pumk');
        $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'macet']))
            ->assertOk()->assertSee('Total Sisa Macet:')->assertSee('Rp800.000')
            ->assertSee('saldo cache berbeda dari kartu');
        $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'macet']))->assertOk();
        $this->assertSame($before, $loan->fresh()->getAttributes());
        $this->assertSame(1, PumkAngsuran::where('pinjaman_id', $loan->id)->count());
    }

    public function test_last_proven_category_at_closure_is_used_if_history_changes_after_closure(): void
    {
        $loan = $this->loan($this->mitra('Mitra Histori', false), 'lancar', '100.00', true);
        foreach (['2026-09-01' => 'macet', '2026-10-01' => 'lancar'] as $date => $value) {
            PumkClassificationHistory::create([
                'pinjaman_id' => $loan->id, 'attribute' => 'kolektibilitas', 'value' => $value,
                'effective_from' => $date, 'source_kind' => 'verified_correction',
                'recorded_at' => now(),
                'source_ref' => 'fixture-'.$date, 'fingerprint' => hash('sha256', $date.$loan->id),
            ]);
        }
        $loan->update(['lunas_at' => '2026-09-15 10:00:00']);

        $result = app(PumkCollectibilitySummaryService::class)->summarize([]);

        $this->assertSame('100.00', $result['nominal']['macet']);
        $this->assertSame('0.00', $result['nominal']['lancar']);
        $this->assertSame('lancar', $loan->fresh()->kolektibilitas);
    }

    public function test_duplicate_names_and_multiple_closure_events_do_not_duplicate_a_loan(): void
    {
        $first = $this->mitra('Nama Sama', false);
        $second = $this->mitra('Nama Sama', false);
        $firstLoan = $this->loan($first, 'macet', '40.00', true);
        $secondLoan = $this->loan($second, 'macet', '60.00', true);
        PumkLoanClosure::create([
            'pinjaman_id' => $firstLoan->id, 'closed_at' => '2026-08-01 10:00:00',
            'reopened_at' => '2026-08-05 10:00:00',
            'settlement_snapshot' => ['lunas_total_saldo' => '900.00'],
        ]);
        $beforeLoan = $firstLoan->fresh()->getAttributes();
        $beforeClosures = $firstLoan->closures()->orderBy('id')->get()->map->getAttributes()->all();

        $result = app(PumkCollectibilitySummaryService::class)->summarize(['q' => 'Nama Sama']);

        $this->assertSame('100.00', $result['nominal']['macet']);
        $this->assertSame('100.00', $result['subtotal']);
        $this->assertSame(0, $result['unknown_balances']);
        $this->actingAs($this->admin(), 'pumk');
        $this->get(route('pumk-admin.mitra.index'))->assertOk();
        $this->get(route('pumk-admin.mitra.index'))->assertOk();
        $this->assertSame($beforeLoan, $firstLoan->fresh()->getAttributes());
        $this->assertSame($beforeClosures, $firstLoan->closures()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(2, $firstLoan->closures()->count());
        $this->assertSame(1, $secondLoan->closures()->count());
    }

    public function test_status_flag_mismatches_are_reported_without_counting_inactive_open_loans(): void
    {
        $inactiveOpen = $this->loan($this->mitra('Mismatch Active'), 'lancar', '500.00');
        $inactiveOpen->update(['is_active' => false]);
        $closed = $this->loan($this->mitra('Mismatch Closed'), 'macet', '50.00', true);
        $closed->update(['is_active' => true]);
        $nonaktif = $this->loan($this->mitra('Nonaktif'), 'diragukan', '70.00');
        $nonaktif->update(['status' => 'nonaktif', 'is_active' => false]);
        $nonaktifMismatch = $this->loan($this->mitra('Nonaktif Mismatch'), 'lancar', '80.00');
        $nonaktifMismatch->update(['status' => 'nonaktif', 'is_active' => true]);

        $result = app(PumkCollectibilitySummaryService::class)->summarize([]);

        $this->assertSame('50.00', $result['subtotal']);
        $this->assertSame(3, $result['status_flag_mismatches']);
        $this->get(route('pumk-admin.mitra.index'))->assertRedirect(route('login'));
    }

    public function test_only_the_verified_source_key_is_excluded_from_recap_and_matching_filter(): void
    {
        $excluded = $this->loan($this->mitra('Dummy Terverifikasi'), 'lancar', '500.00');
        $retained = $this->loan($this->mitra('Mitra Sah'), 'lancar', '700.00');
        config()->set('pumk.recap_excluded_source_keys', [$excluded->source_key]);

        $summary = app(PumkCollectibilitySummaryService::class);
        $this->assertSame('700.00', $summary->summarize([])['subtotal']);
        $this->assertSame([$retained->id], $summary->matchingLoanIds([], 'lancar'));
        $this->assertFalse($summary->isIncluded($excluded));
        $this->assertTrue($summary->isIncluded($retained));
        $this->assertDatabaseHas('pumk_pinjaman', ['id' => $excluded->id]);
    }

    public function test_missing_cache_is_recalculated_when_card_components_are_complete_without_writing(): void
    {
        $loan = $this->loan($this->mitra('Cache Kosong'), 'lancar', '1234.56');
        DB::table('pumk_pinjaman')->where('id', $loan->id)->update(['total_sisa' => null]);
        $before = $loan->fresh()->getAttributes();

        $result = app(PumkCollectibilitySummaryService::class)->summarize([], 'lancar');

        $this->assertSame('1234.56', $result['subtotal']);
        $this->assertSame(0, $result['unknown_balances']);
        $this->assertSame(1, $result['cache_differences']);
        $this->assertSame($before, $loan->fresh()->getAttributes());
    }

    public function test_manual_category_and_matching_filter_advance_with_time_without_payment_or_cache_write(): void
    {
        $loan = PumkPinjaman::create([
            'mitra_id' => $this->mitra('Jadwal Manual')->id,
            'source_key' => (string) Str::uuid(),
            'status' => 'aktif', 'is_active' => true,
            'pinjaman_pokok' => '1200000.00', 'pinjaman_bunga' => '0.00',
            'mulai_angsuran' => '2026-01-01', 'selesai_angsuran' => '2026-12-01',
            'nilai_angsuran_bulanan' => '100000.00',
            'kolektibilitas' => 'lancar', 'total_sisa' => '1200000.00',
        ]);
        $before = $loan->fresh()->getAttributes();
        $summary = app(PumkCollectibilitySummaryService::class);

        CarbonImmutable::setTestNow('2026-01-31');
        try {
            $this->assertSame('1200000.00', $summary->summarize([])['nominal']['lancar']);
            CarbonImmutable::setTestNow('2026-02-28');
            $this->assertSame('1200000.00', $summary->summarize([])['nominal']['kurang_lancar']);
            $this->assertSame([$loan->id], $summary->matchingLoanIds([], 'kurang_lancar'));
            $this->assertSame([], $summary->matchingLoanIds([], 'lancar'));
        } finally {
            CarbonImmutable::setTestNow();
        }
        $this->assertSame($before, $loan->fresh()->getAttributes());
    }

    public function test_filtered_table_displays_the_matching_loan_instead_of_the_latest_other_category(): void
    {
        $mitra = $this->mitra('Dua Pinjaman');
        $matching = $this->loan($mitra, 'macet', '1234.56');
        $this->loan($mitra, 'lancar', '9876.54');

        $this->actingAs($this->admin(), 'pumk')
            ->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'macet']))
            ->assertOk()->assertSee('Rp1.234,56')->assertDontSee('Rp9.876,54')
            ->assertViewHas('mitraList', fn ($list) => $list->first()->pinjaman->first()->id === $matching->id);
    }

    public function test_closure_snapshot_preserves_original_category_even_if_current_field_changes(): void
    {
        $loan = $this->loan($this->mitra('Kategori Penutupan', false), 'lancar', '-193232.00', true);
        $loan->closures()->firstOrFail()->update([
            'settlement_snapshot' => ['lunas_total_saldo' => '-193232.00', 'kolektibilitas' => 'macet'],
        ]);
        $result = app(PumkCollectibilitySummaryService::class)->summarize([]);
        $this->assertSame('-193232.00', $result['nominal']['macet']);
        $this->assertSame('0.00', $result['nominal']['lancar']);
        $this->actingAs($this->admin(), 'pumk')
            ->get(route('pumk-admin.mitra.index', ['status' => 'lunas', 'kolektibilitas' => 'macet']))
            ->assertOk()->assertSee('Kategori Penutupan')->assertSee('Total Sisa Macet:')
            ->assertViewHas('mitraList', fn ($list) => $list->first()->pinjaman->first()->id === $loan->id);

        $loan->closures()->firstOrFail()->update([
            'settlement_snapshot' => ['lunas_total_saldo' => '-193232.00', 'kolektibilitas' => null],
        ]);
        $this->assertSame('-193232.00', app(PumkCollectibilitySummaryService::class)->summarize([])['nominal']['belum_dinilai']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'pumk_admin', 'is_active' => true, 'must_change_password' => false]);
    }

    private function mitra(string $name, bool $active = true): PumkMitra
    {
        return PumkMitra::create([
            'nama_mitra' => $name, 'source_key' => (string) Str::uuid(), 'is_active' => $active,
        ]);
    }

    private function loan(PumkMitra $mitra, ?string $quality, string $balance, bool $closed = false): PumkPinjaman
    {
        $principal = bccomp($balance, '0.00', 2) > 0 ? $balance : '0.00';
        $monthly = match ($quality) {
            'macet' => bcdiv($principal, '15', 2),
            'diragukan' => bcdiv($principal, '7', 2),
            'kurang_lancar' => bcdiv($principal, '2', 2),
            'lancar' => bccomp($principal, '0.00', 2) > 0 ? $principal : '1.00',
            default => null,
        };
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id, 'source_key' => (string) Str::uuid(),
            'status' => $closed ? 'lunas' : 'aktif', 'is_active' => ! $closed,
            'pinjaman_pokok' => $principal,
            'pinjaman_bunga' => '0.00', 'sisa_pokok' => $balance,
            'mulai_angsuran' => $quality === null ? null : '2025-01-01',
            'nilai_angsuran_bulanan' => $monthly,
            'sisa_bunga' => '0.00', 'total_sisa' => $balance,
            'kolektibilitas' => $quality,
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => $balance, 'sisa_bunga' => '0.00',
                'bulan_tunggakan' => match ($quality) {
                    'macet' => 10, 'diragukan' => 7, 'kurang_lancar' => 2,
                    'lancar' => 0, default => null,
                },
                'nilai_tunggakan' => '0.00', 'kolektibilitas' => $quality,
                'total_pokok_masuk' => '0.00', 'total_bunga_masuk' => '0.00',
                'total_denda_masuk' => '0.00',
                'formula_sumber' => $quality === null ? null : [
                    'mulai_angsuran' => '2025-01-01', 'angsuran_bulanan' => $monthly,
                    'total_kewajiban' => $principal, 'tanggal_acuan' => '2026-07-31',
                ],
            ],
            'lunas_at' => $closed ? '2026-09-15 10:00:00' : null,
            'lunas_total_saldo' => $closed ? $balance : null,
        ]);
        if ($closed) {
            PumkLoanClosure::create([
                'pinjaman_id' => $loan->id, 'closed_at' => $loan->lunas_at,
                'settlement_snapshot' => ['lunas_total_saldo' => $balance],
            ]);
        }

        return $loan;
    }
}
