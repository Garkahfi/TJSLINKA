<?php

namespace App\Services\Pumk;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class PumkImportValueParser
{
    public function sourceDate(mixed $raw, bool $date1904, string $field, array &$warnings): ?CarbonImmutable
    {
        if ($this->blank($raw)) {
            return null;
        }

        $date = $this->excelDate($raw, $date1904);
        if ($date === null || $date->year <= 1900) {
            $warnings[] = "invalid_{$field}";

            return null;
        }

        return $date;
    }

    public function excelDate(mixed $raw, bool $date1904): ?CarbonImmutable
    {
        if ($this->blank($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial <= 0) {
                return null;
            }

            $base = $date1904
                ? CarbonImmutable::create(1904, 1, 1, 0, 0, 0, 'UTC')
                : CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC');

            return $base->addDays((int) floor($serial));
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'UTC');
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                // Coba format berikutnya.
            }
        }

        return null;
    }

    public function sourceYear(mixed $raw, ?CarbonImmutable $tanggalPencairan): ?int
    {
        $year = $this->integer($raw);

        if ($year !== null && $year >= 1900 && $year <= 2200) {
            return $year;
        }

        return $tanggalPencairan?->year;
    }

    public function collectibility(mixed $raw, array &$warnings): ?string
    {
        $value = $this->cleanText($raw);
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $map = [
            'lancar' => 'lancar',
            'kurang_lancar' => 'kurang_lancar',
            'diragukan' => 'diragukan',
            'macet' => 'macet',
        ];

        if (! isset($map[$normalized])) {
            $warnings[] = 'unknown_collectibility';

            return $normalized;
        }

        return $map[$normalized];
    }

    public function money(mixed $raw, string $column, array &$warnings): ?string
    {
        $value = $this->decimal($raw);
        if ($value !== null && bccomp($value, '0.00', 2) < 0) {
            $warnings[] = "negative_value_{$column}";
        }

        return $value === null ? null : $this->scale($value, 2);
    }

    /** Normalisasi nilai sumber untuk audit dan perbaikan terbatas data impor. */
    public function sourceMoney(mixed $raw): ?string
    {
        $value = $this->decimal($raw);

        return $value === null ? null : $this->scale($value, 2);
    }

    /** Raw numeric value for read-only reconciliation, before cent formatting. */
    public function sourceDecimal(mixed $raw): ?string
    {
        return $this->decimal($raw);
    }

    public function rate(mixed $raw, string $column, array &$warnings): ?string
    {
        $value = $this->decimal($raw);
        if ($value !== null && bccomp($value, '0', 4) < 0) {
            $warnings[] = "negative_value_{$column}";
        }

        return $value === null ? null : $this->scale($value, 4);
    }

    public function decimal(mixed $raw): ?string
    {
        if ($this->blank($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        $negativeByParentheses = str_starts_with($value, '(') && str_ends_with($value, ')');

        // XLSX dapat menyimpan angka uang sebagai <v>1.5E7</v>. Membuang
        // huruf sebelum mengembangkan eksponen mengubahnya menjadi 1.57.
        $scientific = $negativeByParentheses ? substr($value, 1, -1) : $value;
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/D', $scientific, $parts)) {
            $exponent = (int) $parts[4];
            if ($exponent < -30 || $exponent > 30) {
                return null;
            }

            $digits = $parts[2].($parts[3] ?? '');
            $decimalPosition = strlen($parts[2]) + $exponent;
            $expanded = match (true) {
                $decimalPosition <= 0 => '0.'.str_repeat('0', -$decimalPosition).$digits,
                $decimalPosition >= strlen($digits) => $digits.str_repeat('0', $decimalPosition - strlen($digits)),
                default => substr($digits, 0, $decimalPosition).'.'.substr($digits, $decimalPosition),
            };

            return ($negativeByParentheses || $parts[1] === '-' ? '-' : '').$expanded;
        }

        // Notasi ilmiah yang rusak tidak boleh diam-diam dibaca sebagai angka lain.
        if (preg_match('/\d[eE](?:[+-]?\d*|$)/', $scientific)) {
            return null;
        }

        $value = preg_replace('/[^0-9,\.\-]/', '', $value) ?? '';

        if ($negativeByParentheses) {
            $value = '-'.ltrim($value, '-');
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $last = end($parts);
            $value = strlen((string) $last) <= 2
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        }

        return is_numeric($value) ? $value : null;
    }

    public function scale(string $value, int $scale): string
    {
        return bcadd($value, '0', $scale);
    }

    public function integer(mixed $raw): ?int
    {
        $value = $this->decimal($raw);

        return $value === null ? null : (int) round((float) $value);
    }

    public function positiveInteger(mixed $raw): ?int
    {
        $value = $this->integer($raw);

        return $value !== null && $value > 0 ? $value : null;
    }

    public function cleanText(mixed $raw): ?string
    {
        if ($this->blank($raw)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $raw));

        return $value === '' ? null : $value;
    }

    public function cleanIdentifier(mixed $raw): ?string
    {
        $value = $this->cleanText($raw);

        return $value === null ? null : trim($value);
    }

    public function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
