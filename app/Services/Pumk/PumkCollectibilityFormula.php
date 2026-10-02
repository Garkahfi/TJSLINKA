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
        // AK dapat memiliki pecahan lebih dari dua desimal. Jangan potong
        // sebelum AK x AP; nominal AQ baru dinormalisasi ke sen.
        $installment = (string) $angsuranBulanan;
        $obligation = bcadd((string) $totalKewajiban, '0', 2);
        if (bccomp($installment, '0', 14) <= 0 || bccomp($obligation, '0', 2) < 0) {
            return null;
        }

        $dueCount = 0;
        if ($date->greaterThanOrEqualTo($start)) {
            // Excel DATEDIF(AH, AJ, "m") + 1 counts completed months.
            // AI is stored as the contract end, not a cap on AP.
            $through = $date;
            // DATEDIF counts completed months,
            // retaining the start date's day instead of rounding to month-start.
            $months = ($through->year - $start->year) * 12 + $through->month - $start->month;
            if ($through->day < $start->day) {
                $months--;
            }
            $dueCount = $months + 1;
        }

        // AQ adalah hasil MIN di Excel. Angsuran dengan pecahan sub-sen
        // dibulatkan ke sen sesudah perkalian, bukan dipotong lebih awal.
        $scheduled = bcadd(bcmul($installment, (string) $dueCount, 14), '0.005', 2);
        $due = bccomp($scheduled, $obligation, 2) > 0 ? $obligation : $scheduled;
        $paid = bcadd($pokokDibayar, $bungaDibayar, 2);
        $raw = bcsub($due, $paid, 2);
        // BCMath scale zero truncates toward zero, as Excel ROUNDDOWN(x, 0).
        $months = (int) bcdiv($raw, $installment, 0);

        return [
            'jumlah_jatuh_tempo' => $dueCount,
            'jatuh_tempo_nominal' => $due,
            'tunggakan_mentah' => $raw,
            'bulan_tunggakan' => $months,
            'nilai_tunggakan' => bcmul((string) $months, $installment, 2),
            'kolektibilitas' => match (true) {
                $months >= 10 => 'macet',
                $months >= 7 => 'diragukan',
                $months >= 2 => 'kurang_lancar',
                default => 'lancar',
            },
        ];
    }
}
