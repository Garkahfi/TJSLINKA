<?php

namespace App\Services\Pumk;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Formula jadwal PUMK: nominal jatuh tempo dikurangi pokok+bunga dibayar. */
final class PumkCollectibilityFormula
{
    /**
     * @return array{jumlah_jatuh_tempo:int,jatuh_tempo_nominal:string,tunggakan_mentah:string,bulan_tunggakan:int,nilai_tunggakan:string,kolektibilitas:string}|null
     */
    public function calculate(
        ?CarbonInterface $mulai,
        mixed $angsuranBulanan,
        mixed $totalKewajiban,
        string $pokokDibayar,
        string $bungaDibayar,
        CarbonInterface $asOf,
    ): ?array {
        if ($mulai === null || $angsuranBulanan === null || $totalKewajiban === null) {
            return null;
        }

        $start = CarbonImmutable::instance($mulai)->startOfDay();
        $date = CarbonImmutable::instance($asOf)->startOfDay();
        // AK can contain sub-cent fractions; AQ is not rounded in Excel.
        $installment = (string) $angsuranBulanan;
        $scale = PumkDecimal::scale($installment, $totalKewajiban, $pokokDibayar, $bungaDibayar);
        $obligation = bcadd((string) $totalKewajiban, '0', $scale);
        if (bccomp($installment, '0', $scale) <= 0 || bccomp($obligation, '0', $scale) < 0) {
            return null;
        }

        if ($date->lessThan($start)) {
            // AP is blank in the workbook before the first installment date.
            return null;
        }

        // Excel DATEDIF(AH, AJ, "m") + 1 counts completed months. AI does
        // not cap AP; retain AH's day instead of rounding to month-start.
        $months = ($date->year - $start->year) * 12 + $date->month - $start->month;
        if ($date->day < $start->day) {
            $months--;
        }
        $dueCount = $months + 1;

        // AQ has no ROUND in Excel. Preserve AK's full source precision until
        // after AR is calculated, including at category boundaries.
        $scheduled = bcmul($installment, (string) $dueCount, $scale);
        $due = bccomp($scheduled, $obligation, $scale) > 0 ? $obligation : $scheduled;
        $paid = bcadd($pokokDibayar, $bungaDibayar, $scale);
        $raw = bcsub($due, $paid, $scale);
        // BCMath scale zero truncates toward zero, as Excel ROUNDDOWN(x, 0).
        $months = (int) bcdiv($raw, $installment, 0);

        return [
            'jumlah_jatuh_tempo' => $dueCount,
            'jatuh_tempo_nominal' => $due,
            'tunggakan_mentah' => $raw,
            'bulan_tunggakan' => $months,
            'nilai_tunggakan' => bcmul((string) $months, $installment, $scale),
            'kolektibilitas' => match (true) {
                $months >= 10 => 'macet',
                $months >= 7 => 'diragukan',
                $months >= 2 => 'kurang_lancar',
                default => 'lancar',
            },
        ];
    }
}
