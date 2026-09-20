<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PumkLoanDocumentService
{
    public function __construct(private readonly PumkActivityLogger $activity) {}

    public function replace(PumkPinjaman $pinjaman, string $type, UploadedFile $file): PumkPinjamanDokumen
    {
        $contractField = PumkPinjamanDokumen::CONTRACT_FIELDS[$type] ?? null;
        if ($contractField === null) {
            throw ValidationException::withMessages(['dokumen' => 'Jenis dokumen kontrak tidak valid.']);
        }
        if (! filled($pinjaman->getAttribute($contractField))) {
            throw ValidationException::withMessages([
                "dokumen_{$type}" => 'Dokumen hanya dapat diunggah jika nomor kontraknya telah diisi.',
            ]);
        }

        $path = $file->store("pumk/pinjaman/{$pinjaman->id}/kontrak", 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(["dokumen_{$type}" => 'Dokumen gagal disimpan.']);
        }

        try {
            return DB::transaction(function () use ($pinjaman, $type, $file, $path): PumkPinjamanDokumen {
                $old = PumkPinjamanDokumen::query()
                    ->where('pinjaman_id', $pinjaman->id)
                    ->where('jenis_dokumen', $type)
                    ->lockForUpdate()
                    ->first();
                $oldPath = $old?->file_path;
                $document = PumkPinjamanDokumen::updateOrCreate(
                    ['pinjaman_id' => $pinjaman->id, 'jenis_dokumen' => $type],
                    [
                        'file_path' => $path,
                        'nama_file_asli' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                        'file_size' => (int) $file->getSize(),
                        'uploaded_by' => auth('pumk')->id(),
                        'uploaded_at' => now(),
                    ],
                );
                $this->activity->record(
                    $old ? 'replace_contract_document' : 'upload_contract_document',
                    'pumk_internal',
                    $old ? 'Mengganti dokumen kontrak pinjaman.' : 'Mengunggah dokumen kontrak pinjaman.',
                    $pinjaman,
                    metadata: ['jenis_dokumen' => $type],
                );
                if ($oldPath && $oldPath !== $path) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function delete(PumkPinjamanDokumen $document): void
    {
        DB::transaction(function () use ($document): void {
            $locked = PumkPinjamanDokumen::query()->lockForUpdate()->findOrFail($document->id);
            $path = $locked->file_path;
            $pinjaman = $locked->pinjaman;
            $type = $locked->jenis_dokumen;
            $locked->delete();
            $this->activity->record(
                'delete_contract_document',
                'pumk_internal',
                'Menghapus dokumen kontrak pinjaman.',
                $pinjaman,
                metadata: ['jenis_dokumen' => $type],
            );
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }
}
