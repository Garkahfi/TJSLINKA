<?php

namespace App\Observers;

use App\Models\PumkAngsuran;
use App\Models\PumkPinjaman;
use App\Services\Pumk\PiutangCalculator;

class PumkAngsuranObserver
{
    public function saving(PumkAngsuran $angsuran): void
    {
        $this->captureBaseline($angsuran->pinjaman_id);
        if ($angsuran->isDirty('pinjaman_id') && $angsuran->exists) {
            $this->captureBaseline($angsuran->getOriginal('pinjaman_id'));
        }
    }

    public function deleting(PumkAngsuran $angsuran): void
    {
        $this->captureBaseline($angsuran->pinjaman_id);
    }

    public function saved(PumkAngsuran $angsuran): void
    {
        $this->sync($angsuran->pinjaman_id);
        if ($angsuran->wasChanged('pinjaman_id')) {
            $this->sync($angsuran->getOriginal('pinjaman_id'));
        }
    }

    public function deleted(PumkAngsuran $angsuran): void
    {
        $this->sync($angsuran->pinjaman_id);
    }

    private function captureBaseline(?int $id): void
    {
        if ($loan = PumkPinjaman::find($id)) {
            app(PiutangCalculator::class)->simpanBaselineSumber($loan);
        }
    }

    private function sync(?int $id): void
    {
        if ($loan = PumkPinjaman::find($id)) {
            app(PiutangCalculator::class)->sinkronkanCache($loan);
        }
    }
}
