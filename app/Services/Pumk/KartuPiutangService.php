<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class KartuPiutangService
{
    public function __construct(private readonly PiutangCalculator $calculator) {}

    /**
     * Hanya dipanggil untuk satu kartu. Bulan kosong tidak membuat transaksi DB.
     * Saldo historis agregat dan transaksi di luar tenor ikut memperlebar
     * rentang kartu. Bulan di antaranya tetap ditampilkan agar urutan histori
     * tidak terputus, tanpa membuat transaksi DB rekaan.
     *
     * @return array<string, mixed>
     */
    public function buat(
        PumkPinjaman $pinjaman,
        ?CarbonInterface $today = null,
        int|string|null $tahun = null,
    ): array
    {
        $pinjaman->loadMissing(['saldoAwal', 'angsuran']);
        $calculation = $this->calculator->hitungUntukPinjaman($pinjaman, $today);
        $start = $pinjaman->mulai_angsuran ? CarbonImmutable::instance($pinjaman->mulai_angsuran)->startOfDay() : null;
        $end = $pinjaman->selesai_angsuran ? CarbonImmutable::instance($pinjaman->selesai_angsuran)->startOfDay() : null;
        $validDates = $start && $end && $end->greaterThanOrEqualTo($start);
        $tenor = $validDates
            ? (int) $start->startOfMonth()->diffInMonths($end->startOfMonth()) + 1
            : null;
        $months = [];

        for ($i = 0; $i < ($tenor ?? 0); $i++) {
            $date = $start->addMonthsNoOverflow($i);
            $months[$date->format('Y-m')] = ['no' => $i + 1, 'tanggal' => $date];
        }

        $payments = $pinjaman->angsuran->groupBy(fn ($payment) => $payment->periode->format('Y-m'));
        foreach ($payments->keys() as $month) {
            $months[$month] ??= [
                'no' => null,
                'tanggal' => CarbonImmutable::instance($payments->get($month)->first()->periode),
            ];
        }

        $opening = $pinjaman->saldoAwal;
        $openingPrincipal = (string) ($opening?->pokok_masuk ?? '0.00');
        $openingInterest = (string) ($opening?->bunga_masuk ?? '0.00');
        $openingMonth = $opening?->cutoff_date?->format('Y-m');
        $hasOpening = bccomp($openingPrincipal, '0', 2) !== 0 || bccomp($openingInterest, '0', 2) !== 0;
        if ($hasOpening && $openingMonth) {
            $months[$openingMonth] ??= ['no' => null, 'tanggal' => CarbonImmutable::instance($opening->cutoff_date)];
        }

        // Snapshot Excel dapat memuat saldo historis yang tidak bisa diurai per
        // bulan. Tampilkan sebagai penyesuaian sumber, bukan pembayaran rekaan.
        $principal = (string) ($pinjaman->pinjaman_pokok ?? '0.00');
        $interest = (string) ($pinjaman->pinjaman_bunga ?? '0.00');
        $principalAdjustment = bcsub((string) $calculation['sisa_pokok'], bcsub($principal, (string) $calculation['total_pokok_masuk'], 2), 2);
        $interestAdjustment = bcsub((string) $calculation['sisa_bunga'], bcsub($interest, (string) $calculation['total_bunga_masuk'], 2), 2);
        $hasAdjustment = bccomp($principalAdjustment, '0', 2) !== 0 || bccomp($interestAdjustment, '0', 2) !== 0;
        $sourceMonth = $pinjaman->source_updated_at?->format('Y-m');
        if ($hasAdjustment && $sourceMonth) {
            $months[$sourceMonth] ??= ['no' => null, 'tanggal' => CarbonImmutable::instance($pinjaman->source_updated_at)];
        }

        // Rentang mengikuti bulan paling awal dan paling akhir dari jadwal
        // resmi, pembayaran nyata, saldo awal, dan penyesuaian sumber.
        // Baris bulan kosong hanya dibentuk di memori untuk satu kartu.
        if ($months !== []) {
            ksort($months);
            $firstMonth = array_key_first($months);
            $lastMonth = array_key_last($months);
            $cursor = CarbonImmutable::createFromFormat('!Y-m', $firstMonth)->startOfMonth();
            $lastDate = CarbonImmutable::createFromFormat('!Y-m', $lastMonth)->startOfMonth();
            $completeMonths = [];

            while ($cursor->lessThanOrEqualTo($lastDate)) {
                $month = $cursor->format('Y-m');
                $completeMonths[$month] = $months[$month] ?? [
                    'no' => null,
                    'tanggal' => $cursor,
                ];
                $cursor = $cursor->addMonth();
            }

            $months = $completeMonths;
        }

        ksort($months);
        $rows = [];
        $referenceDate = $today
            ? CarbonImmutable::instance($today)
            : CarbonImmutable::today();
        $currentMonth = $referenceDate->format('Y-m');
        $historicalBoundary = $referenceDate->subYear()->startOfMonth();
        foreach ($months as $month => $period) {
            $number = $period['no'];
            $date = $period['tanggal'];
            $monthlyPayments = $payments->get($month, collect());
            $singlePayment = $monthlyPayments->count() === 1 ? $monthlyPayments->first() : null;
            $hasPayment = $monthlyPayments->isNotEmpty();
            $paidPrincipal = $monthlyPayments->isEmpty() ? null : $monthlyPayments->reduce(fn ($total, $payment) => bcadd($total, (string) $payment->pokok, 2), '0.00');
            $paidInterest = $monthlyPayments->isEmpty() ? null : $monthlyPayments->reduce(fn ($total, $payment) => bcadd($total, (string) $payment->bunga, 2), '0.00');
            $notes = [];

            if ($hasOpening && $month === $openingMonth) {
                $principal = bcsub($principal, $openingPrincipal, 2);
                $interest = bcsub($interest, $openingInterest, 2);
                $notes[] = 'Akumulasi pembayaran sampai '.$opening->cutoff_date->translatedFormat('d F Y');
            }

            $principal = bcsub($principal, $paidPrincipal ?? '0.00', 2);
            $interest = bcsub($interest, $paidInterest ?? '0.00', 2);
            if ($hasAdjustment && $month === $sourceMonth) {
                $principal = bcadd($principal, $principalAdjustment, 2);
                $interest = bcadd($interest, $interestAdjustment, 2);
                $notes[] = 'Penyesuaian saldo sumber per '.$pinjaman->source_updated_at->translatedFormat('d F Y');
            }
            if ($number === null && $monthlyPayments->isNotEmpty()) {
                $notes[] = 'Pembayaran di luar tenor';
            }

            $rows[] = [
                'no' => $number,
                'tanggal' => $date,
                'nomor_bukti' => $monthlyPayments->pluck('nomor_bukti')->filter()->implode(', '),
                'has_payment' => $hasPayment,
                'pokok_dibayar' => $paidPrincipal,
                'bunga_dibayar' => $paidInterest,
                // Denda dicatat terpisah; rumus kartu fisik: total = pokok + bunga.
                'total_dibayar' => $paidPrincipal === null ? null : bcadd($paidPrincipal, $paidInterest, 2),
                'saldo_pokok' => $principal,
                'saldo_bunga' => $interest,
                'is_bulan_berjalan' => $month === $currentMonth,
                'is_historis' => $date->startOfMonth()->lessThan($historicalBoundary),
                'catatan' => implode('. ', $notes),
                'bukti_pembayaran' => $singlePayment && filled($singlePayment->bukti_pembayaran_path)
                    ? [
                        'angsuran_id' => $singlePayment->id,
                        'nama_asli' => $singlePayment->bukti_pembayaran_nama_asli,
                        'mime' => $singlePayment->bukti_pembayaran_mime,
                    ]
                    : null,
                'editable_angsuran' => $singlePayment
                    && $singlePayment->batch_id === null
                    && $singlePayment->created_by !== null
                        ? [
                            'id' => $singlePayment->id,
                            'periode' => $singlePayment->periode->format('Y-m'),
                            'nomor_bukti' => $singlePayment->nomor_bukti,
                            'pokok' => (string) $singlePayment->pokok,
                            'bunga' => (string) $singlePayment->bunga,
                            'denda' => (string) $singlePayment->denda,
                            'bukti_nama_asli' => $singlePayment->bukti_pembayaran_nama_asli,
                            'has_bukti' => filled($singlePayment->bukti_pembayaran_path),
                        ]
                        : null,
            ];
        }

        $allRows = $rows;
        $availableYears = collect($allRows)
            ->map(fn (array $row): int => (int) $row['tanggal']->year)
            ->unique()->sortDesc()->values()->all();
        // Pemanggil perhitungan internal yang tidak meminta filter tetap menerima
        // seluruh histori. Halaman/ekspor mengirim "terbaru" secara eksplisit.
        $selectedYear = $tahun === null || $tahun === 'semua'
            ? 'semua'
            : (is_numeric($tahun) ? (int) $tahun : null);
        if ($selectedYear !== 'semua' && ! in_array($selectedYear, $availableYears, true)) {
            $selectedYear = $availableYears[0] ?? 'semua';
        }

        $yearRows = $selectedYear === 'semua'
            ? $allRows
            : array_values(array_filter($allRows, fn (array $row): bool => $row['tanggal']->year === $selectedYear));
        // Baris kosong dibentuk hanya di memori untuk menjaga struktur kartu.
        // Tidak ada transaksi nol yang dibuat di database.
        $rows = $yearRows;
        $closingRow = $yearRows === [] ? null : $yearRows[array_key_last($yearRows)];
        $filteredShortage = $closingRow
            ? bcadd((string) $closingRow['saldo_pokok'], (string) $closingRow['saldo_bunga'], 2)
            : bcadd($principal, $interest, 2);

        return [
            'tenor' => $tenor,
            'jadwal' => $rows,
            'kekurangan' => $selectedYear === 'semua' ? bcadd($principal, $interest, 2) : $filteredShortage,
            'calculation' => $calculation,
            'jadwal_error' => $validDates ? null : 'Jadwal angsuran belum dapat ditampilkan lengkap. Periksa tanggal Angsuran Pertama dan Jatuh Tempo pada Edit Data.',
            'has_history' => $hasOpening
                || $hasAdjustment
                || $payments->keys()->contains(
                    fn (string $month): bool => $start === null || $month < $start->format('Y-m'),
                ),
            'denda' => $calculation['total_denda_masuk'],
            'tahun_tersedia' => $availableYears,
            'tahun_terpilih' => $selectedYear,
            'periode_label' => $selectedYear === 'semua'
                ? 'Menampilkan seluruh histori Kartu Piutang'
                : 'Menampilkan periode: Tahun '.$selectedYear,
        ];
    }

}
