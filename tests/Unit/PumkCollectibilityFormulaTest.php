<?php

namespace Tests\Unit;

use App\Services\Pumk\PumkCollectibilityFormula;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PumkCollectibilityFormulaTest extends TestCase
{
    private PumkCollectibilityFormula $formula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formula = new PumkCollectibilityFormula;
    }

    public function test_rounddown_uses_unrounded_arrears_and_truncates_negative_values_toward_zero(): void
    {
        $start = CarbonImmutable::parse('2026-01-01');
        $asOf = CarbonImmutable::parse('2026-03-01');

        $before = $this->formula->calculate($start, '100000.00', '1200000.00', '10000.00', '0.00', $asOf);
        $after = $this->formula->calculate($start, '100000.00', '1200000.00', '30000.00', '0.00', $asOf);
        $negative = $this->formula->calculate($start, '100000.00', '1200000.00', '490000.00', '0.00', $asOf);

        $this->assertSame('290000.00', $before['tunggakan_mentah']);
        $this->assertSame(2, $before['bulan_tunggakan']);
        $this->assertSame('200000.00', $before['nilai_tunggakan']);
        $this->assertSame('270000.00', $after['tunggakan_mentah']);
        $this->assertSame(2, $after['bulan_tunggakan']);
        $this->assertSame('-190000.00', $negative['tunggakan_mentah']);
        $this->assertSame(-1, $negative['bulan_tunggakan']);
        $this->assertSame('-100000.00', $negative['nilai_tunggakan']);
        $this->assertSame('lancar', $negative['kolektibilitas']);
    }

    public function test_each_category_boundary_uses_whole_months_without_float(): void
    {
        $start = CarbonImmutable::parse('2026-01-01');
        $asOf = CarbonImmutable::parse('2026-10-01');
        foreach ([
            ['900000.01', 0, 'lancar'],
            ['800000.01', 1, 'lancar'],
            ['800000.00', 2, 'kurang_lancar'],
            ['400000.00', 6, 'kurang_lancar'],
            ['300000.00', 7, 'diragukan'],
            ['100000.00', 9, 'diragukan'],
            ['0.00', 10, 'macet'],
        ] as [$paid, $months, $category]) {
            $result = $this->formula->calculate($start, '100000.00', '2400000.00', $paid, '0.00', $asOf);
            $this->assertSame($months, $result['bulan_tunggakan']);
            $this->assertSame($category, $result['kolektibilitas']);
        }
    }

    public function test_due_months_follow_day_boundary_without_capping_at_contract_end(): void
    {
        $start = CarbonImmutable::parse('2026-01-05');
        $expected = [
            '2026-01-04' => 0, '2026-01-05' => 1,
            '2026-02-04' => 1, '2026-02-05' => 2, '2026-02-06' => 2,
            '2026-12-31' => 12,
        ];
        foreach ($expected as $date => $count) {
            $result = $this->formula->calculate($start, '100000.00', '250000.00', '0.00', '0.00', CarbonImmutable::parse($date));
            $this->assertSame($count, $result['jumlah_jatuh_tempo'], $date);
        }
        $capped = $this->formula->calculate($start, '100000.00', '250000.00', '0.00', '0.00', CarbonImmutable::parse('2026-12-31'));
        $this->assertSame('250000.00', $capped['jatuh_tempo_nominal']);
        $this->assertSame(2, $capped['bulan_tunggakan']);

        $monthEnd = $this->formula->calculate(
            CarbonImmutable::parse('2026-01-31'),
            '100000.00', '300000.00', '0.00', '0.00', CarbonImmutable::parse('2026-02-28'),
        );
        $this->assertSame(1, $monthEnd['jumlah_jatuh_tempo']);
    }

    public function test_missing_or_invalid_schedule_is_unrated_not_lancar(): void
    {
        $date = CarbonImmutable::parse('2026-07-31');
        $this->assertNull($this->formula->calculate(null, '100.00', '1000.00', '0.00', '0.00', $date));
        $this->assertNull($this->formula->calculate($date, '0.00', '1000.00', '0.00', '0.00', $date));
        $this->assertNull($this->formula->calculate($date, '100.00', '-1.00', '0.00', '0.00', $date));
    }

    public function test_official_snapshot_cases_and_sub_cent_installment_rounding(): void
    {
        $date = CarbonImmutable::parse('2026-07-31');
        $loan97 = $this->formula->calculate(
            CarbonImmutable::parse('2026-01-01'), '621950', '22388850',
            '12440000', '0', $date,
        );
        $this->assertSame(7, $loan97['jumlah_jatuh_tempo']);
        $this->assertSame('4353650.00', $loan97['jatuh_tempo_nominal']);
        $this->assertSame(-13, $loan97['bulan_tunggakan']);
        $this->assertSame('lancar', $loan97['kolektibilitas']);

        $loan199 = $this->formula->calculate(
            CarbonImmutable::parse('2024-01-01'), '1409890.02894891',
            '51353616.04', '0', '0', $date,
        );
        $this->assertSame(31, $loan199['jumlah_jatuh_tempo']);
        $this->assertSame('43706590.90', $loan199['jatuh_tempo_nominal']);
    }
}
