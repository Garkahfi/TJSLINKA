<?php

namespace App\Services\Pumk;

use InvalidArgumentException;

class PumkSettlementTolerance
{
    public function amount(): string
    {
        $raw = config('pumk.settlement_tolerance', '0.00');
        if (! is_string($raw) && ! is_int($raw)) {
            throw new InvalidArgumentException('Konfigurasi toleransi pelunasan PUMK tidak valid.');
        }

        $value = (string) $raw;
        if (! preg_match('/^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Konfigurasi toleransi pelunasan PUMK harus berupa rupiah nonnegatif dengan maksimal dua desimal.');
        }

        return bcadd($value, '0.00', 2);
    }
}
