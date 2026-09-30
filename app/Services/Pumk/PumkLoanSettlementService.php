<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkLoanClosure;
use App\Models\PumkPinjaman;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PumkLoanSettlementService
{
    public function __construct(
        private readonly PumkSettlementTolerance $tolerances,
        private readonly PiutangCalculator $calculator,
        private readonly PumkActivityLogger $activity,
    ) {}

    /** @return array<string, mixed> */
    public function preview(PumkPinjaman $loan, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now('Asia/Jakarta');
        // Pelunasan memakai total akhir yang sama dengan Kartu Piutang saat ini.
        // Resolver posisi historis tidak menjadi dasar keputusan pelunasan manual.
        $card = $this->calculator->hitungUntukPinjaman($loan, $at);
        $baseline = $loan->baseline_sumber ?? [
            'sisa_pokok' => $loan->sisa_pokok, 'sisa_bunga' => $loan->sisa_bunga,
        ];
        $hasPrincipal = ($loan->source_updated_at !== null && isset($baseline['sisa_pokok']))
            || $loan->pinjaman_pokok !== null;
        $hasInterest = ($loan->source_updated_at !== null && isset($baseline['sisa_bunga']))
            || $loan->pinjaman_bunga !== null;
        $known = $hasPrincipal && $hasInterest;
        $tolerance = $this->tolerances->amount();
        $result = [
            'known' => $known,
            'saldo_pokok' => $known ? (string) $card['sisa_pokok'] : null,
            'saldo_bunga' => $known ? (string) $card['sisa_bunga'] : null,
            'total' => $known ? (string) $card['total_sisa'] : null,
            'as_of_date' => $at->setTimezone('Asia/Jakarta')->toDateString(),
            'source_kind' => 'kartu_piutang', 'reason_code' => null,
            'eligible' => false, 'reason' => null, 'tolerance' => $tolerance,
            'needs_note' => false, 'message' => null,
        ];
        if (! $known) {
            $result['reason_code'] = 'missing_card_balance';
            $result['message'] = 'Dasar saldo kartu piutang belum lengkap. Lengkapi data sumber sebelum menandai lunas.';

            return $result;
        }

        $sign = bccomp($result['total'], '0.00', 2);
        if ($sign <= 0 || bccomp($result['total'], $tolerance, 2) <= 0) {
            $result['eligible'] = true;
            $result['reason'] = match ($sign) {
                -1 => PumkPinjaman::LUNAS_KELEBIHAN_BAYAR,
                0 => PumkPinjaman::LUNAS_NORMAL,
                default => PumkPinjaman::LUNAS_TOLERANSI,
            };
            $result['needs_note'] = $sign !== 0;

            return $result;
        }

        $result['reason_code'] = 'above_tolerance';
        $result['message'] = 'Sisa pinjaman melebihi batas toleransi Rp '.number_format((float) $tolerance, 0, ',', '.').'.';

        return $result;
    }

    /** @return array{status:string,reason:?string} */
    public function settle(int $mitraId, int $loanId, ?string $note, int $actorId): array
    {
        return DB::transaction(function () use ($mitraId, $loanId, $note, $actorId): array {
            $mitra = PumkMitra::query()->lockForUpdate()->findOrFail($mitraId);
            $loan = PumkPinjaman::query()->where('mitra_id', $mitra->id)->lockForUpdate()->findOrFail($loanId);
            if ($loan->status === PumkPinjaman::STATUS_LUNAS) {
                return ['status' => 'already_paid', 'reason' => $loan->lunas_reason];
            }
            abort_unless($loan->status === PumkPinjaman::STATUS_AKTIF && $loan->is_active, 409, 'Hanya pinjaman aktif yang dapat ditandai lunas.');

            $loan->load(['saldoAwal', 'angsuran']);
            $decision = $this->preview($loan);
            if (! $decision['eligible']) {
                throw ValidationException::withMessages(['lunas' => $decision['message']]);
            }
            $note = trim((string) $note);
            if ($decision['needs_note'] && $note === '') {
                throw ValidationException::withMessages(['lunas_note' => 'Alasan penyelesaian wajib diisi untuk selisih atau kelebihan bayar.']);
            }

            $closedAt = now();
            $loan->forceFill([
                'status' => PumkPinjaman::STATUS_LUNAS,
                'is_active' => false,
                'lunas_at' => $closedAt,
                'lunas_by' => $actorId,
                'lunas_note' => $note !== '' ? $note : null,
                'lunas_reason' => $decision['reason'],
                'lunas_saldo_pokok' => $decision['saldo_pokok'],
                'lunas_saldo_bunga' => $decision['saldo_bunga'],
                'lunas_total_saldo' => $decision['total'],
                'lunas_tolerance_applied' => $decision['tolerance'],
            ])->save();
            PumkLoanClosure::create([
                'pinjaman_id' => $loan->id, 'closed_at' => $closedAt, 'closed_by' => $actorId,
                'settlement_snapshot' => $loan->only([
                    'lunas_reason', 'lunas_note', 'lunas_saldo_pokok', 'lunas_saldo_bunga',
                    'lunas_total_saldo', 'lunas_tolerance_applied',
                ]),
            ]);
            $this->activity->record('mark_loan_paid', 'pumk_internal', 'Menandai pinjaman sebagai lunas.', $loan, metadata: [
                'reason' => $decision['reason'],
                'saldo_pokok' => $decision['saldo_pokok'],
                'saldo_bunga' => $decision['saldo_bunga'],
                'total_saldo' => $decision['total'],
                'tolerance' => $decision['tolerance'],
            ]);

            $hasActiveLoan = $mitra->pinjaman()->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true)->exists();
            $mitra->update(['is_active' => $hasActiveLoan]);
            if (! $hasActiveLoan) {
                $this->activity->record('archive_partner', 'pumk_internal', 'Memindahkan Mitra ke arsip karena tidak memiliki pinjaman aktif.', $mitra, null);
            }

            DB::table('pumk_monitoring_reports')
                ->whereDate('as_of_date', '>=', $closedAt->copy()->timezone('Asia/Jakarta')->toDateString())
                ->update(['needs_reconcile' => true, 'updated_at' => now()]);

            return ['status' => 'paid', 'reason' => $decision['reason']];
        }, 3);
    }

    public function reopen(int $mitraId, int $loanId, string $note, int $actorId): void
    {
        if (mb_strlen(trim($note)) < 5) {
            throw ValidationException::withMessages(['reopen_note' => 'Isi alasan membuka kembali pinjaman (minimal 5 karakter).']);
        }
        DB::transaction(function () use ($mitraId, $loanId, $note, $actorId): void {
            $mitra = PumkMitra::query()->lockForUpdate()->findOrFail($mitraId);
            $loan = $mitra->pinjaman()->lockForUpdate()->findOrFail($loanId);
            abort_unless($loan->status === PumkPinjaman::STATUS_LUNAS, 409, 'Hanya pinjaman lunas yang dapat dibuka kembali.');
            if ($loan->lunas_at === null) {
                throw ValidationException::withMessages(['reopen_note' => 'Tanggal penutupan lama belum tersedia. Periksa data penutupan sebelum membuka kembali.']);
            }
            $closure = $loan->closures()->whereNull('reopened_at')->lockForUpdate()->latest('id')->first();
            $closure ??= $loan->closures()->create([
                'closed_at' => $loan->lunas_at, 'closed_by' => $loan->lunas_by,
                'settlement_snapshot' => $loan->only([
                    'lunas_reason', 'lunas_note', 'lunas_saldo_pokok', 'lunas_saldo_bunga',
                    'lunas_total_saldo', 'lunas_tolerance_applied',
                ]),
            ]);
            $reopenedAt = now();
            $closure->update(['reopened_at' => $reopenedAt, 'reopened_by' => $actorId, 'reopen_note' => trim($note)]);
            $loan->forceFill([
                'status' => PumkPinjaman::STATUS_AKTIF, 'is_active' => true,
                'lunas_at' => null, 'lunas_by' => null, 'lunas_note' => null, 'lunas_reason' => null,
                'lunas_saldo_pokok' => null, 'lunas_saldo_bunga' => null,
                'lunas_total_saldo' => null, 'lunas_tolerance_applied' => null,
            ])->save();
            $mitra->update(['is_active' => true]);
            $this->activity->record('reopen_loan', 'pumk_internal', 'Membuka kembali pinjaman lama; saldo dan angsuran tetap.', $loan,
                metadata: ['closure_id' => $closure->id]);
            DB::table('pumk_monitoring_reports')
                ->whereDate('as_of_date', '>=', $reopenedAt->copy()->timezone('Asia/Jakarta')->toDateString())
                ->update(['needs_reconcile' => true, 'updated_at' => now()]);
        }, 3);
    }
}
