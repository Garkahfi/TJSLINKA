<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PumkLoanBalanceResolver
{
    /** @return array{known:bool,saldo_pokok:?string,saldo_bunga:?string,total:?string,as_of_date:string,source_kind:?string,reason_code:?string} */
    public function resolve(PumkPinjaman $loan, CarbonImmutable $asOf): array
    {
        $asOf = $asOf->setTimezone('Asia/Jakarta')->startOfDay();
        $loan->loadMissing(['saldoAwal', 'angsuran']);
        $unknown = fn (string $reason): array => [
            'known' => false, 'saldo_pokok' => null, 'saldo_bunga' => null,
            'total' => null, 'as_of_date' => $asOf->toDateString(),
            'source_kind' => null, 'reason_code' => $reason,
        ];

        if ($loan->source_updated_at !== null) {
            if ($this->localDate($loan->source_updated_at)->greaterThan($asOf)) {
                return $unknown('baseline_after_cutoff');
            }
            $baseline = $loan->baseline_sumber ?? [];
            if (! isset($baseline['sisa_pokok'], $baseline['sisa_bunga'])) {
                return $unknown('missing_import_baseline');
            }
            $principal = (string) $baseline['sisa_pokok'];
            $interest = (string) $baseline['sisa_bunga'];
            foreach ($loan->angsuran as $payment) {
                if ($payment->batch_id !== null || $payment->created_at === null
                    || $payment->created_at->lessThanOrEqualTo($loan->source_updated_at)
                    || $payment->periode === null || $this->localDate($payment->periode)->greaterThan($asOf)) {
                    continue;
                }
                $principal = bcsub($principal, (string) $payment->pokok, 2);
                $interest = bcsub($interest, (string) $payment->bunga, 2);
            }
            $sourceKind = 'baseline_sumber';
        } else {
            if ($loan->pinjaman_pokok === null) {
                return $unknown('missing_principal');
            }
            if ($loan->pinjaman_bunga === null) {
                return $unknown('missing_interest');
            }
            $opening = $loan->saldoAwal;
            if ($opening !== null && $this->localDate($opening->cutoff_date)->greaterThan($asOf)) {
                return $unknown('opening_after_cutoff');
            }
            $principal = bcsub((string) $loan->pinjaman_pokok, (string) ($opening?->pokok_masuk ?? '0.00'), 2);
            $interest = bcsub((string) $loan->pinjaman_bunga, (string) ($opening?->bunga_masuk ?? '0.00'), 2);
            foreach ($loan->angsuran as $payment) {
                if ($payment->periode === null || $this->localDate($payment->periode)->greaterThan($asOf)
                    || ($opening !== null && $this->localDate($payment->periode)->lessThanOrEqualTo($this->localDate($opening->cutoff_date)))) {
                    continue;
                }
                $principal = bcsub($principal, (string) $payment->pokok, 2);
                $interest = bcsub($interest, (string) $payment->bunga, 2);
            }
            $sourceKind = $opening ? 'saldo_awal' : 'riwayat_rinci';
        }

        return [
            'known' => true,
            'saldo_pokok' => $principal,
            'saldo_bunga' => $interest,
            'total' => bcadd($principal, $interest, 2),
            'as_of_date' => $asOf->toDateString(),
            'source_kind' => $sourceKind,
            'reason_code' => null,
        ];
    }

    private function localDate(CarbonInterface $date): CarbonImmutable
    {
        // Tanggal acuan workbook dan periode angsuran adalah tanggal bisnis,
        // bukan waktu UTC yang harus digeser ke hari berikutnya.
        return CarbonImmutable::parse($date->toDateString(), 'Asia/Jakarta')->startOfDay();
    }
}
