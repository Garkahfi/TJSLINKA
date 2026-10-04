<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PumkInstallmentChanges
{
    public function __construct(private readonly PumkInstallmentInput $input) {}

    public function storeAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        PumkMitraOwnership::loan($mitra, $pinjaman);
        abort_unless($pinjaman->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active, 409, 'Pinjaman yang sudah selesai tidak dapat menerima angsuran baru.');
        $data = $this->input->validateAngsuran($request);

        $periode = CarbonImmutable::createFromFormat('!Y-m', $data['periode'])->startOfMonth();
        $alreadyExists = $pinjaman->angsuran()->whereDate('periode', $periode)->exists();

        if ($alreadyExists) {
            return back()
                ->withErrors(['periode' => 'Angsuran untuk periode tersebut sudah tercatat.'])
                ->withInput();
        }

        $confirmed = $request->boolean('hapus_saldo_awal');
        $saldoAwal = $pinjaman->saldoAwal()->first();
        if ($saldoAwal !== null
            && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)
            && ! $confirmed) {
            return $this->saldoAwalConflictResponse(
                $request,
                $saldoAwal->cutoff_date,
                $periode,
                route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]),
                'POST',
            );
        }

        DB::transaction(function () use ($data, $periode, $mitra, $pinjaman, $calculator, $confirmed, $request, $proofs): void {
            PumkMitra::query()->lockForUpdate()->findOrFail($mitra->id);
            $lockedLoan = PumkPinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            abort_unless($lockedLoan->status === PumkPinjaman::STATUS_AKTIF && $lockedLoan->is_active, 409, 'Pinjaman yang sudah selesai tidak dapat menerima angsuran baru.');
            abort_if($lockedLoan->angsuran()->whereDate('periode', $periode)->exists(), 409, 'Angsuran periode ini sudah tercatat.');
            $saldoAwal = $lockedLoan->saldoAwal()->lockForUpdate()->first();

            if ($saldoAwal !== null && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)) {
                abort_unless($confirmed, 409, 'Saldo Awal berubah. Muat ulang halaman lalu konfirmasi kembali.');
                $saldoAwal->delete();
                $calculator->bangunUlangBaselineTanpaSaldoAwal($lockedLoan);
            }

            $angsuran = PumkAngsuran::create([
                'pinjaman_id' => $lockedLoan->id,
                'periode' => $periode,
                'nomor_bukti' => filled($data['nomor_bukti'] ?? null) ? trim($data['nomor_bukti']) : null,
                'pokok' => $data['pokok'],
                'bunga' => $data['bunga'],
                'denda' => $data['denda'] ?? 0,
                'created_by' => auth('pumk')->id(),
            ]);
            if ($request->hasFile('bukti_pembayaran')) {
                $proofs->replace($angsuran, $request->file('bukti_pembayaran'));
            }
        });

        app(PumkActivityLogger::class)->record('create_installment', 'pumk_internal', 'Menambahkan angsuran manual.', $pinjaman, metadata: ['periode' => $periode->format('Y-m')]);

        return redirect()
            ->route('pumk-admin.mitra.show', $mitra)
            ->with('success', 'Angsuran berhasil ditambahkan dan perhitungan kartu piutang telah diperbarui.');
    }

    public function updateAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        PumkMitraOwnership::loan($mitra, $pinjaman);
        abort_unless($pinjaman->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active, 409, 'Angsuran pada pinjaman yang sudah selesai tidak dapat diubah.');
        abort_unless($angsuran->pinjaman_id === $pinjaman->id, 404);

        // Data yang berasal dari workbook adalah data sumber. Koreksi terhadap
        // data tersebut harus dilakukan lewat proses impor, bukan form manual.
        abort_if(
            $angsuran->batch_id !== null || $angsuran->created_by === null,
            403,
            'Angsuran hasil impor tidak dapat diedit dari kartu piutang.',
        );

        $data = $this->input->validateAngsuran($request);
        $periode = CarbonImmutable::createFromFormat('!Y-m', $data['periode'])->startOfMonth();
        $alreadyExists = $pinjaman->angsuran()
            ->whereKeyNot($angsuran->id)
            ->whereDate('periode', $periode)
            ->exists();

        if ($alreadyExists) {
            return back()
                ->withErrors(['periode' => 'Angsuran untuk periode tersebut sudah tercatat.'])
                ->withInput();
        }

        $confirmed = $request->boolean('hapus_saldo_awal');
        $saldoAwal = $pinjaman->saldoAwal()->first();
        if ($saldoAwal !== null
            && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)
            && ! $confirmed) {
            return $this->saldoAwalConflictResponse(
                $request,
                $saldoAwal->cutoff_date,
                $periode,
                route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $angsuran]),
                'PATCH',
            );
        }

        DB::transaction(function () use ($angsuran, $data, $periode, $mitra, $pinjaman, $calculator, $confirmed, $request, $proofs): void {
            PumkMitra::query()->lockForUpdate()->findOrFail($mitra->id);
            $lockedLoan = PumkPinjaman::query()->lockForUpdate()->findOrFail($pinjaman->id);
            abort_unless($lockedLoan->status === PumkPinjaman::STATUS_AKTIF && $lockedLoan->is_active, 409, 'Angsuran pada pinjaman yang sudah selesai tidak dapat diubah.');
            $lockedPayment = $lockedLoan->angsuran()->lockForUpdate()->findOrFail($angsuran->id);
            abort_if($lockedPayment->batch_id !== null || $lockedPayment->created_by === null, 403,
                'Angsuran hasil impor tidak dapat diedit dari kartu piutang.');
            abort_if($lockedLoan->angsuran()->whereKeyNot($lockedPayment->id)->whereDate('periode', $periode)->exists(), 409, 'Angsuran periode ini sudah tercatat.');
            $saldoAwal = $lockedLoan->saldoAwal()->lockForUpdate()->first();

            if ($saldoAwal !== null && $this->periodeTumpangTindihSaldoAwal($periode, $saldoAwal->cutoff_date)) {
                abort_unless($confirmed, 409, 'Saldo Awal berubah. Muat ulang halaman lalu konfirmasi kembali.');
                $saldoAwal->delete();
                $calculator->bangunUlangBaselineTanpaSaldoAwal($lockedLoan);
            }

            $lockedPayment->update([
                'periode' => $periode,
                'nomor_bukti' => filled($data['nomor_bukti'] ?? null) ? trim($data['nomor_bukti']) : null,
                'pokok' => $data['pokok'],
                'bunga' => $data['bunga'],
                'denda' => $data['denda'],
            ]);
            if ($request->hasFile('bukti_pembayaran')) {
                $proofs->replace($lockedPayment->fresh(), $request->file('bukti_pembayaran'));
            }
        });

        app(PumkActivityLogger::class)->record('update_installment', 'pumk_internal', 'Memperbarui angsuran manual.', $pinjaman, metadata: ['periode' => $periode->format('Y-m')]);

        return redirect()
            ->route('pumk-admin.mitra.show', $mitra)
            ->with('success', 'Angsuran berhasil diperbarui dan saldo kartu piutang telah dihitung ulang.');
    }

    private function periodeTumpangTindihSaldoAwal(
        CarbonImmutable $periode,
        ?CarbonInterface $cutoffDate,
    ): bool {
        return $cutoffDate !== null
            && $periode->startOfMonth()->lessThanOrEqualTo(CarbonImmutable::instance($cutoffDate)->startOfMonth());
    }

    private function saldoAwalConflictResponse(
        Request $request,
        CarbonInterface $cutoffDate,
        CarbonImmutable $periode,
        string $action,
        string $method,
    ): RedirectResponse {
        $cutoff = CarbonImmutable::instance($cutoffDate);
        $message = 'Pinjaman ini memiliki Saldo Awal per '.$cutoff->translatedFormat('d F Y').'. '
            .'Angsuran periode '.$periode->translatedFormat('F Y').' tumpang tindih dengan akumulasi tersebut. '
            .'Jika dilanjutkan, Saldo Awal akan dihapus agar pembayaran tidak dihitung ganda.';

        return back()
            ->withErrors(['periode' => $message])
            ->withInput()
            ->with('saldo_awal_overlap', [
                'action' => $action,
                'method' => $method,
                'cutoff' => $cutoff->translatedFormat('d F Y'),
                'periode' => $periode->translatedFormat('F Y'),
                'proof_was_uploaded' => $request->hasFile('bukti_pembayaran'),
            ]);
    }
}
