<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PumkPaymentProofService
{
    public function __construct(private readonly PumkActivityLogger $activity) {}

    public function replace(PumkAngsuran $angsuran, UploadedFile $file): PumkAngsuran
    {
        $this->ensureManualPayment($angsuran);
        $path = $file->store("pumk/angsuran/{$angsuran->id}/bukti", 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['bukti_pembayaran' => 'Bukti pembayaran gagal disimpan.']);
        }

        try {
            return DB::transaction(function () use ($angsuran, $file, $path): PumkAngsuran {
                $locked = PumkAngsuran::query()->lockForUpdate()->findOrFail($angsuran->id);
                $this->ensureManualPayment($locked);
                $oldPath = $locked->bukti_pembayaran_path;
                $replacing = filled($oldPath);
                $locked->forceFill([
                    'bukti_pembayaran_path' => $path,
                    'bukti_pembayaran_nama_asli' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'bukti_pembayaran_mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                    'bukti_pembayaran_size' => (int) $file->getSize(),
                    'bukti_pembayaran_uploaded_at' => now(),
                ])->saveQuietly();
                $this->activity->record(
                    $replacing ? 'replace_payment_proof' : 'upload_payment_proof',
                    'pumk_internal',
                    $replacing ? 'Mengganti bukti pembayaran angsuran.' : 'Mengunggah bukti pembayaran angsuran.',
                    $locked,
                    metadata: ['pinjaman_id' => $locked->pinjaman_id],
                );
                if ($replacing && $oldPath !== $path) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }

                return $locked;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function delete(PumkAngsuran $angsuran): void
    {
        DB::transaction(function () use ($angsuran): void {
            $locked = PumkAngsuran::query()->lockForUpdate()->findOrFail($angsuran->id);
            $this->ensureManualPayment($locked);
            $path = $locked->bukti_pembayaran_path;
            if (! filled($path)) {
                return;
            }
            $locked->forceFill([
                'bukti_pembayaran_path' => null,
                'bukti_pembayaran_nama_asli' => null,
                'bukti_pembayaran_mime' => null,
                'bukti_pembayaran_size' => null,
                'bukti_pembayaran_uploaded_at' => null,
            ])->saveQuietly();
            $this->activity->record(
                'delete_payment_proof',
                'pumk_internal',
                'Menghapus bukti pembayaran angsuran.',
                $locked,
                metadata: ['pinjaman_id' => $locked->pinjaman_id],
            );
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }

    private function ensureManualPayment(PumkAngsuran $angsuran): void
    {
        if ($angsuran->batch_id !== null || $angsuran->created_by === null) {
            throw new AuthorizationException('Bukti angsuran hasil impor tidak dapat diubah dari Kartu Piutang.');
        }
    }
}
