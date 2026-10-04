<?php

namespace App\Services\Pumk;

/** Decimal helpers for source values that contain fractions of a rupiah. */
final class PumkDecimal
{
    public static function scale(mixed ...$values): int
    {
        $scale = 2;
        foreach ($values as $value) {
            $text = (string) ($value ?? '0');
            $point = strpos($text, '.');
            if ($point !== false) {
                $scale = max($scale, strlen($text) - $point - 1);
            }
        }

        return $scale;
    }

    public static function roundCents(string $value): string
    {
        // Excel's displayed two-decimal rupiah amount is rounded only after
        // the underlying source calculation. BCMath otherwise truncates.
        return bccomp($value, '0', self::scale($value)) < 0
            ? bcsub($value, '0.005', 2)
            : bcadd($value, '0.005', 2);
    }
}
