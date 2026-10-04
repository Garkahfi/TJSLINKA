<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class PiutangCalculator
{
    public function __construct(private readonly PumkCollectibilityFormula $collectibilityFormula) {}

    /**
     * Menghasilkan angka kartu piutang tanpa mengubah nilai sumber Excel.
     *
     * Snapshot workbook adalah baseline saldo/pembayaran. Posisi kolektibilitas
     * dihitung kembali dari jadwal sheet resmi pada tanggal yang diminta.
     *
     * @return array<string, int|string|bool|null>
     */
    public function hitungUntukPinjaman(
        PumkPinjaman $pinjaman,
        ?CarbonInterface $tanggalAcuan = null,
    ): array {
        $pinjaman->loadMissing(['saldoAwal', 'angsuran']);
        $hasilImpor = $pinjaman->source_updated_at !== null;
        $baseline = $pinjaman->baseline_sumber ?? $this->baselineSumber($pinjaman);
        $formulaSumber = $baseline['formula_sumber'] ?? [];
        $tanggalSumber = $formulaSumber['tanggal_acuan'] ?? $pinjaman->source_updated_at?->toDateString();
        if ($hasilImpor && $tanggalAcuan === null && ! $tanggalSumber) {
            throw new LogicException('Tanggal acuan sumber PUMK belum tersedia.');
        }
        $asOf = $tanggalAcuan !== null
            ? CarbonImmutable::instance($tanggalAcuan)
            : ($hasilImpor ? CarbonImmutable::parse($tanggalSumber) : CarbonImmutable::today());
        if ($tanggalAcuan !== null && $pinjaman->source_updated_at !== null
            && $asOf->toDateString() < $pinjaman->source_updated_at->toDateString()) {
            throw new LogicException('Posisi sebelum snapshot impor belum dapat direkonstruksi dari data yang tersedia.');
        }
        $posisiKantor = $hasilImpor && $tanggalAcuan === null;
        $effectivePayments = $pinjaman->angsuran->filter(
            fn ($item) => $posisiKantor || $item->periode === null
                // Periode adalah bulan bisnis, bukan timestamp. Bandingkan YYYY-MM
                // agar cast UTC dari DB tidak bergeser melampaui awal bulan WIB.
                || $item->periode->format('Y-m') <= $asOf->format('Y-m'),
        );

        $saldoAwal = $pinjaman->saldoAwal;
        $openingIncluded = $saldoAwal !== null && $saldoAwal->cutoff_date !== null
            && ($posisiKantor || $saldoAwal->cutoff_date->toDateString() <= $asOf->toDateString());
        $pokokMasuk = $this->add(
            $openingIncluded ? $saldoAwal->pokok_masuk : null,
            ...$effectivePayments->pluck('pokok')->all(),
        );
        $bungaMasuk = $this->add(
            $openingIncluded ? $saldoAwal->bunga_masuk : null,
            ...$effectivePayments->pluck('bunga')->all(),
        );
        $dendaMasuk = $this->add(
            $openingIncluded ? $saldoAwal->denda : null,
            ...$effectivePayments->pluck('denda')->all(),
        );

        $sisaPokokHitung = $this->sub($pinjaman->pinjaman_pokok, $pokokMasuk);
        $sisaBungaHitung = $this->sub($pinjaman->pinjaman_bunga, $bungaMasuk);

        // Nilai sisa/tunggakan dari workbook merupakan snapshot terakhir sumber.
        // Angsuran manual yang dicatat sesudah snapshot harus tetap tercermin di
        // kartu piutang tanpa menimpa baseline historis tersebut.
        $angsuranManualSesudahSnapshot = $hasilImpor
            ? $effectivePayments->filter(
                fn ($item) => $item->batch_id === null
                    && $item->created_at !== null
                    && $item->created_at->greaterThan($pinjaman->source_updated_at),
            )
            : collect();
        $pokokManualBaru = $this->sub($pokokMasuk, $baseline['total_pokok_masuk'] ?? null);
        $bungaManualBaru = $this->sub($bungaMasuk, $baseline['total_bunga_masuk'] ?? null);
        // Replacing the aggregate opening balance with verified detailed
        // history creates a different payment representation. Preserve the
        // original source cells for audit, but calculate from that explicit
        // replacement baseline rather than subtracting the old opening twice.
        $sourceRawApplies = ! ($baseline['manual_history_replaces_opening'] ?? false);
        $rawPokok = ($sourceRawApplies ? ($formulaSumber['sisa_pokok_raw'] ?? null) : null)
            ?? $baseline['sisa_pokok'] ?? null;
        $rawBunga = ($sourceRawApplies ? ($formulaSumber['sisa_bunga_raw'] ?? null) : null)
            ?? $baseline['sisa_bunga'] ?? null;
        $rawTotal = $sourceRawApplies ? ($formulaSumber['total_sisa_raw'] ?? null) : null;
        $sisaPokokRaw = $hasilImpor && $rawPokok !== null
            ? bcsub((string) $rawPokok, $pokokManualBaru, PumkDecimal::scale($rawPokok, $pokokManualBaru))
            : $sisaPokokHitung;
        $sisaBungaRaw = $hasilImpor && $rawBunga !== null
            ? bcsub((string) $rawBunga, $bungaManualBaru, PumkDecimal::scale($rawBunga, $bungaManualBaru))
            : $sisaBungaHitung;
        $sisaPokok = PumkDecimal::roundCents($sisaPokokRaw);
        $sisaBunga = PumkDecimal::roundCents($sisaBungaRaw);
        $totalSisaRaw = $hasilImpor && $rawTotal !== null
            ? bcsub(bcsub((string) $rawTotal, $pokokManualBaru,
                PumkDecimal::scale($rawTotal, $pokokManualBaru)), $bungaManualBaru,
                PumkDecimal::scale($rawTotal, $pokokManualBaru, $bungaManualBaru))
            : bcadd($sisaPokokRaw, $sisaBungaRaw, PumkDecimal::scale($sisaPokokRaw, $sisaBungaRaw));

        if ($hasilImpor) {
            $mulaiSumber = isset($formulaSumber['mulai_angsuran'])
                ? CarbonImmutable::parse($formulaSumber['mulai_angsuran'])
                : $pinjaman->mulai_angsuran;
            $pokokSumber = $sourceRawApplies ? ($formulaSumber['pokok_masuk_raw'] ?? null) : null;
            $bungaSumber = $sourceRawApplies ? ($formulaSumber['bunga_masuk_raw'] ?? null) : null;
            $pokokEfektif = bcadd((string) ($pokokSumber ?? $baseline['total_pokok_masuk'] ?? $pokokMasuk),
                $pokokManualBaru, PumkDecimal::scale($pokokSumber, $pokokManualBaru));
            $bungaEfektif = bcadd((string) ($bungaSumber ?? $baseline['total_bunga_masuk'] ?? $bungaMasuk),
                $bungaManualBaru, PumkDecimal::scale($bungaSumber, $bungaManualBaru));
            $schedule = $this->collectibilityFormula->calculate(
                $mulaiSumber,
                $formulaSumber['angsuran_bulanan'] ?? $pinjaman->nilai_angsuran_bulanan,
                $formulaSumber['total_kewajiban'] ?? $pinjaman->total_pinjaman,
                $pokokEfektif,
                $bungaEfektif,
                $asOf,
            );
            $bulanTunggakan = $schedule['bulan_tunggakan'] ?? null;
            $nilaiTunggakan = $schedule['nilai_tunggakan'] ?? null;
            $kolektibilitas = $schedule['kolektibilitas'] ?? null;
            $menggunakanBaselineSumber = true;
        } else {
            $schedule = $this->collectibilityFormula->calculate(
                $pinjaman->mulai_angsuran,
                $pinjaman->nilai_angsuran_bulanan,
                $pinjaman->pinjaman_pokok !== null && $pinjaman->pinjaman_bunga !== null
                    ? $this->add($pinjaman->pinjaman_pokok, $pinjaman->pinjaman_bunga)
                    : null,
                $pokokMasuk,
                $bungaMasuk,
                $asOf,
            );
            $bulanTunggakan = $schedule['bulan_tunggakan'] ?? null;
            $nilaiTunggakan = $schedule['nilai_tunggakan'] ?? null;
            $kolektibilitas = $schedule['kolektibilitas'] ?? null;
            $menggunakanBaselineSumber = false;
        }

        return [
            'total_pokok_masuk' => $pokokMasuk,
            'total_bunga_masuk' => $bungaMasuk,
            'total_denda_masuk' => $dendaMasuk,
            'total_masuk' => $this->add($pokokMasuk, $bungaMasuk, $dendaMasuk),
            'sisa_pokok' => $sisaPokok,
            'sisa_bunga' => $sisaBunga,
            'total_sisa' => PumkDecimal::roundCents($totalSisaRaw),
            'total_sisa_raw' => $totalSisaRaw,
            'sisa_pokok_raw' => $sisaPokokRaw,
            'sisa_bunga_raw' => $sisaBungaRaw,
            'bulan_tunggakan' => $bulanTunggakan,
            'nilai_tunggakan' => $nilaiTunggakan,
            'kolektibilitas' => $kolektibilitas,
            'jumlah_jatuh_tempo' => $schedule['jumlah_jatuh_tempo'] ?? null,
            'jatuh_tempo_nominal' => $schedule['jatuh_tempo_nominal'] ?? null,
            'tunggakan_mentah' => $schedule['tunggakan_mentah'] ?? null,
            'tanggal_acuan' => $asOf->toDateString(),
            'menggunakan_baseline_sumber' => $menggunakanBaselineSumber,
            'jumlah_angsuran_manual_setelah_snapshot' => $angsuranManualSesudahSnapshot->count(),
            'catatan_perhitungan' => $menggunakanBaselineSumber
                ? ($schedule === null
                    ? 'Jadwal atau kewajiban sumber belum lengkap; kolektibilitas posisi ini belum dapat dinilai.'
                    : (isset($baseline['formula_sumber'])
                        ? 'Saldo mengikuti baseline dan perubahan bersih pembayaran; kolektibilitas dihitung dari jadwal sheet resmi.'
                        : 'Saldo mengikuti baseline dan perubahan bersih pembayaran; presisi jadwal sumber perlu dilengkapi untuk verifikasi penuh.'))
                : ($schedule === null
                    ? 'Kolektibilitas belum dapat dinilai karena data jadwal atau kewajiban tidak lengkap.'
                    : 'Kolektibilitas dihitung dari jadwal dan pokok+bunga yang tercatat.'),
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
                'manual_history_replaces_opening' => true,
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

    private function money(mixed $value): string
    {
        // Model casts and import baselines already contain decimal strings.
        // Converting them to float loses cents on large balances.
        return bcadd((string) ($value ?? '0.00'), '0.00', 2);
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
}
