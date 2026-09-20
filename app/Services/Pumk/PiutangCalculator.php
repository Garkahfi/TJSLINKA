<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class PiutangCalculator
{
    /**
     * Menghasilkan angka kartu piutang tanpa mengubah nilai sumber Excel.
     *
     * Untuk pinjaman hasil impor, bulan tunggakan dan kolektibilitas dari
     * workbook dipertahankan sebagai baseline karena rumus resminya masih
     * menunggu validasi bisnis. Pinjaman yang dibuat manual memakai estimasi
     * transparan berdasarkan jadwal dan pembayaran yang tercatat.
     *
     * @return array<string, int|string|bool|null>
     */
    public function hitungUntukPinjaman(
        PumkPinjaman $pinjaman,
        ?CarbonInterface $tanggalAcuan = null,
    ): array {
        $pinjaman->loadMissing(['saldoAwal', 'angsuran']);

        $saldoAwal = $pinjaman->saldoAwal;
        $pokokMasuk = $this->add(
            $saldoAwal?->pokok_masuk,
            $pinjaman->angsuran->sum(fn ($item) => (float) $item->pokok),
        );
        $bungaMasuk = $this->add(
            $saldoAwal?->bunga_masuk,
            $pinjaman->angsuran->sum(fn ($item) => (float) $item->bunga),
        );
        $dendaMasuk = $this->add(
            $saldoAwal?->denda,
            $pinjaman->angsuran->sum(fn ($item) => (float) $item->denda),
        );

        $sisaPokokHitung = $this->sub($pinjaman->pinjaman_pokok, $pokokMasuk);
        $sisaBungaHitung = $this->sub($pinjaman->pinjaman_bunga, $bungaMasuk);
        $hasilImpor = $pinjaman->source_updated_at !== null;

        // Nilai sisa/tunggakan dari workbook merupakan snapshot terakhir sumber.
        // Angsuran manual yang dicatat sesudah snapshot harus tetap tercermin di
        // kartu piutang tanpa menimpa baseline historis tersebut.
        $angsuranManualSesudahSnapshot = $hasilImpor
            ? $pinjaman->angsuran->filter(
                fn ($item) => $item->batch_id === null
                    && $item->created_at !== null
                    && $item->created_at->greaterThan($pinjaman->source_updated_at),
            )
            : collect();
        $baseline = $pinjaman->baseline_sumber ?? $this->baselineSumber($pinjaman);
        $pokokManualBaru = $this->sub($pokokMasuk, $baseline['total_pokok_masuk']);
        $bungaManualBaru = $this->sub($bungaMasuk, $baseline['total_bunga_masuk']);
        $dendaManualBaru = $this->sub($dendaMasuk, $baseline['total_denda_masuk']);
        $hasAdjustment = bccomp($pokokManualBaru, '0', 2) !== 0
            || bccomp($bungaManualBaru, '0', 2) !== 0
            || bccomp($dendaManualBaru, '0', 2) !== 0;

        $sisaPokok = $hasilImpor && $baseline['sisa_pokok'] !== null
            ? $this->sub($baseline['sisa_pokok'], $pokokManualBaru)
            : $sisaPokokHitung;
        $sisaBunga = $hasilImpor && $baseline['sisa_bunga'] !== null
            ? $this->sub($baseline['sisa_bunga'], $bungaManualBaru)
            : $sisaBungaHitung;

        if ($hasilImpor && $baseline['bulan_tunggakan'] !== null) {
            $nilaiTunggakan = $this->nonNegative(bcsub(
                $this->money($baseline['nilai_tunggakan']),
                $this->add($pokokManualBaru, $bungaManualBaru, $dendaManualBaru),
                2,
            ));
            $bulanTunggakan = $this->adjustedImportedArrearsMonths(
                (int) $baseline['bulan_tunggakan'],
                $nilaiTunggakan,
                $pinjaman->nilai_angsuran_bulanan,
                $hasAdjustment,
            );
            $kolektibilitas = $hasAdjustment
                ? $this->kolektibilitasDari($bulanTunggakan)
                : $baseline['kolektibilitas'];
            $menggunakanBaselineSumber = true;
        } else {
            [$bulanTunggakan, $nilaiTunggakan] = $this->estimasiTunggakan(
                $pinjaman,
                $pokokMasuk,
                $bungaMasuk,
                $tanggalAcuan ?? CarbonImmutable::today(),
            );
            $kolektibilitas = $this->kolektibilitasDari($bulanTunggakan);
            $menggunakanBaselineSumber = false;
        }

        return [
            'total_pokok_masuk' => $pokokMasuk,
            'total_bunga_masuk' => $bungaMasuk,
            'total_denda_masuk' => $dendaMasuk,
            'total_masuk' => $this->add($pokokMasuk, $bungaMasuk, $dendaMasuk),
            'sisa_pokok' => $sisaPokok,
            'sisa_bunga' => $sisaBunga,
            'total_sisa' => $this->add($sisaPokok, $sisaBunga),
            'bulan_tunggakan' => $bulanTunggakan,
            'nilai_tunggakan' => $nilaiTunggakan,
            'kolektibilitas' => $kolektibilitas,
            'menggunakan_baseline_sumber' => $menggunakanBaselineSumber,
            'jumlah_angsuran_manual_setelah_snapshot' => $angsuranManualSesudahSnapshot->count(),
            'catatan_perhitungan' => $menggunakanBaselineSumber
                ? ($hasAdjustment
                    ? 'Saldo sumber disesuaikan dengan perubahan pembayaran yang tercatat.'
                    : 'Tunggakan dan kolektibilitas mengikuti baseline workbook sumber.')
                : 'Tunggakan merupakan estimasi sistem sampai rumus bisnis resmi disahkan.',
        ];
    }

    /**
     * Snapshot sumber disimpan terpisah agar penyegaran berulang idempoten.
     *
     * @return array<string, int|string|bool|null>
     */
    public function sinkronkanCache(PumkPinjaman $pinjaman): array
    {
        $pinjaman->unsetRelation('saldoAwal')->unsetRelation('angsuran');
        $this->simpanBaselineSumber($pinjaman);
        $hasil = $this->hitungUntukPinjaman($pinjaman);

        $pinjaman->forceFill([
            'sisa_pokok' => $hasil['sisa_pokok'],
            'sisa_bunga' => $hasil['sisa_bunga'],
            'total_sisa' => $hasil['total_sisa'],
            'bulan_tunggakan' => $hasil['bulan_tunggakan'],
            'nilai_tunggakan' => $hasil['nilai_tunggakan'],
            'kolektibilitas' => $hasil['kolektibilitas'],
            'calculated_at' => now(),
        ])->save();

        return $hasil;
    }

    /** Simpan sebelum mutasi pembayaran, atau setelah seluruh baris impor selesai. */
    public function simpanBaselineSumber(PumkPinjaman $pinjaman, bool $replace = false): void
    {
        if ($pinjaman->source_updated_at === null || (! $replace && $pinjaman->baseline_sumber !== null)) {
            return;
        }

        $pinjaman->unsetRelation('saldoAwal')->unsetRelation('angsuran');
        $pinjaman->forceFill(['baseline_sumber' => $this->baselineSumber($pinjaman)])->saveQuietly();
    }

    /**
     * Bangun ulang snapshot setelah saldo awal agregat sengaja diganti dengan
     * histori angsuran rinci. Tanpa langkah ini, nilai sisa dari workbook yang
     * sudah mencakup saldo awal masih dapat mengurangi pinjaman secara diam-diam.
     */
    public function bangunUlangBaselineTanpaSaldoAwal(PumkPinjaman $pinjaman): void
    {
        if ($pinjaman->source_updated_at === null) {
            $pinjaman->forceFill(['baseline_sumber' => null])->saveQuietly();

            return;
        }

        $pinjaman->unsetRelation('saldoAwal')->unsetRelation('angsuran');
        $pinjaman->loadMissing('angsuran');
        $payments = $this->pembayaranDalamSnapshot($pinjaman);
        $pokokMasuk = $this->add(...$payments->pluck('pokok')->all());
        $bungaMasuk = $this->add(...$payments->pluck('bunga')->all());
        $dendaMasuk = $this->add(...$payments->pluck('denda')->all());
        $existing = $pinjaman->baseline_sumber ?? [];

        $pinjaman->forceFill([
            'baseline_sumber' => array_merge($existing, [
                'sisa_pokok' => $this->sub($pinjaman->pinjaman_pokok, $pokokMasuk),
                'sisa_bunga' => $this->sub($pinjaman->pinjaman_bunga, $bungaMasuk),
                'bulan_tunggakan' => $existing['bulan_tunggakan'] ?? $pinjaman->bulan_tunggakan,
                'nilai_tunggakan' => $existing['nilai_tunggakan'] ?? $pinjaman->nilai_tunggakan,
                'kolektibilitas' => $existing['kolektibilitas'] ?? $pinjaman->kolektibilitas,
                'total_pokok_masuk' => $pokokMasuk,
                'total_bunga_masuk' => $bungaMasuk,
                'total_denda_masuk' => $dendaMasuk,
            ]),
        ])->saveQuietly();
    }

    /** @return array<string, mixed> */
    private function baselineSumber(PumkPinjaman $pinjaman): array
    {
        $pinjaman->loadMissing(['saldoAwal', 'angsuran']);
        $payments = $this->pembayaranDalamSnapshot($pinjaman);

        return [
            'sisa_pokok' => $pinjaman->sisa_pokok,
            'sisa_bunga' => $pinjaman->sisa_bunga,
            'bulan_tunggakan' => $pinjaman->bulan_tunggakan,
            'nilai_tunggakan' => $pinjaman->nilai_tunggakan,
            'kolektibilitas' => $pinjaman->kolektibilitas,
            'total_pokok_masuk' => $this->add($pinjaman->saldoAwal?->pokok_masuk, ...$payments->pluck('pokok')->all()),
            'total_bunga_masuk' => $this->add($pinjaman->saldoAwal?->bunga_masuk, ...$payments->pluck('bunga')->all()),
            'total_denda_masuk' => $this->add($pinjaman->saldoAwal?->denda, ...$payments->pluck('denda')->all()),
        ];
    }

    /** @return Collection<int, PumkAngsuran> */
    private function pembayaranDalamSnapshot(PumkPinjaman $pinjaman): Collection
    {
        return $pinjaman->angsuran->filter(fn ($item) => $item->batch_id !== null
            || $item->created_at === null || $pinjaman->source_updated_at === null
            || $item->created_at->lessThanOrEqualTo($pinjaman->source_updated_at));
    }

    public function kolektibilitasDari(int $bulanTunggakan): string
    {
        return match (true) {
            $bulanTunggakan > 9 => 'macet',
            $bulanTunggakan > 6 => 'diragukan',
            $bulanTunggakan > 1 => 'kurang_lancar',
            default => 'lancar',
        };
    }

    private function adjustedImportedArrearsMonths(
        int $baselineMonths,
        string $adjustedArrearsValue,
        mixed $monthlyInstallment,
        bool $hasManualAdjustment,
    ): int {
        if (! $hasManualAdjustment) {
            return $baselineMonths;
        }

        $installment = $this->money($monthlyInstallment);
        if (bccomp($adjustedArrearsValue, '0.00', 2) <= 0) {
            return 0;
        }

        if (bccomp($installment, '0.00', 2) <= 0) {
            return $baselineMonths;
        }

        return (int) ceil((float) bcdiv($adjustedArrearsValue, $installment, 6));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function estimasiTunggakan(
        PumkPinjaman $pinjaman,
        string $pokokMasuk,
        string $bungaMasuk,
        CarbonInterface $tanggalAcuan,
    ): array {
        if ($pinjaman->mulai_angsuran === null || $pinjaman->nilai_angsuran_bulanan === null) {
            return [0, '0.00'];
        }

        $angsuranBulanan = $this->money($pinjaman->nilai_angsuran_bulanan);

        if (bccomp($angsuranBulanan, '0.00', 2) <= 0) {
            return [0, '0.00'];
        }

        $mulai = CarbonImmutable::instance($pinjaman->mulai_angsuran)->startOfMonth();
        $acuan = CarbonImmutable::instance($tanggalAcuan)->startOfMonth();

        if ($acuan->lessThan($mulai)) {
            return [0, '0.00'];
        }

        if ($pinjaman->selesai_angsuran !== null) {
            $selesai = CarbonImmutable::instance($pinjaman->selesai_angsuran)->startOfMonth();
            $acuan = $acuan->min($selesai);
        }

        $bulanBerjalan = $mulai->diffInMonths($acuan) + 1;
        $seharusnyaMasuk = bcmul($angsuranBulanan, (string) $bulanBerjalan, 2);
        $totalPinjaman = $this->money($pinjaman->total_pinjaman);

        if (bccomp($totalPinjaman, '0.00', 2) > 0 && bccomp($seharusnyaMasuk, $totalPinjaman, 2) > 0) {
            $seharusnyaMasuk = $totalPinjaman;
        }

        $aktualMasuk = $this->add($pokokMasuk, $bungaMasuk);
        $nilaiTunggakan = bcsub($seharusnyaMasuk, $aktualMasuk, 2);

        if (bccomp($nilaiTunggakan, '0.00', 2) <= 0) {
            return [0, '0.00'];
        }

        $bulanTunggakan = (int) ceil((float) bcdiv($nilaiTunggakan, $angsuranBulanan, 6));

        return [$bulanTunggakan, $nilaiTunggakan];
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function add(mixed ...$values): string
    {
        $total = '0.00';

        foreach ($values as $value) {
            $total = bcadd($total, $this->money($value), 2);
        }

        return $total;
    }

    private function sub(mixed $left, mixed $right): string
    {
        return bcsub($this->money($left), $this->money($right), 2);
    }

    private function nonNegative(string $value): string
    {
        return bccomp($value, '0.00', 2) < 0 ? '0.00' : $value;
    }
}
