<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;

class PumkMitraOwnership
{
    public static function loan(PumkMitra $mitra, PumkPinjaman $pinjaman): void
    {
        abort_unless($pinjaman->mitra_id === $mitra->id, 404);
    }

    public static function document(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): void {
        self::loan($mitra, $pinjaman);
        abort_unless($document->pinjaman_id === $pinjaman->id, 404);
    }

    public static function installment(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): void {
        self::loan($mitra, $pinjaman);
        abort_unless($angsuran->pinjaman_id === $pinjaman->id, 404);
    }
}
