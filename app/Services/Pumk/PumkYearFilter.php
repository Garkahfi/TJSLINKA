<?php

namespace App\Services\Pumk;

use Illuminate\Http\Request;

class PumkYearFilter
{
    public static function requestedYear(Request $request): int|string|null
    {
        $value = $request->query('tahun');
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === 'semua') {
            return 'semua';
        }
        abort_unless(is_string($value) && preg_match('/^\d{4}$/', $value) === 1, 422, 'Filter tahun tidak valid.');
        $year = (int) $value;
        abort_unless($year >= 1900 && $year <= 2100, 422, 'Filter tahun tidak valid.');

        return $year;
    }

    public static function yearFileLabel(int|string $year): string
    {
        return $year === 'semua' ? 'semua-tahun' : (string) $year;
    }
}
