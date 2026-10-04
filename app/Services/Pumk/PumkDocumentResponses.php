<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkDocumentResponses
{
    public function viewDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        PumkMitraOwnership::document($mitra, $pinjaman, $document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->response($document->file_path, $document->nama_file_asli, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $document->nama_file_asli).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function downloadDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        PumkMitraOwnership::document($mitra, $pinjaman, $document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_file_asli, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroyDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
        PumkLoanDocumentService $documents,
    ): RedirectResponse {
        PumkMitraOwnership::document($mitra, $pinjaman, $document);
        $documents->delete($document);

        return back()->with('success', 'Dokumen kontrak berhasil dihapus.');
    }

    public function viewPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        PumkMitraOwnership::installment($mitra, $pinjaman, $angsuran);
        $disk = Storage::disk('local');
        abort_unless(filled($angsuran->bukti_pembayaran_path) && $disk->exists($angsuran->bukti_pembayaran_path), 404);

        return $disk->response(
            $angsuran->bukti_pembayaran_path,
            $angsuran->bukti_pembayaran_nama_asli,
            [
                'Content-Type' => $angsuran->bukti_pembayaran_mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    public function downloadPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        PumkMitraOwnership::installment($mitra, $pinjaman, $angsuran);
        $disk = Storage::disk('local');
        abort_unless(filled($angsuran->bukti_pembayaran_path) && $disk->exists($angsuran->bukti_pembayaran_path), 404);

        return $disk->download(
            $angsuran->bukti_pembayaran_path,
            $angsuran->bukti_pembayaran_nama_asli,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'],
        );
    }

    public function destroyPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        PumkMitraOwnership::installment($mitra, $pinjaman, $angsuran);
        abort_unless($pinjaman->status === PumkPinjaman::STATUS_AKTIF && $pinjaman->is_active, 409, 'Bukti pada pinjaman yang sudah selesai tidak dapat diubah.');
        $proofs->delete($angsuran);

        return back()->with('success', 'Bukti pembayaran berhasil dihapus. Data angsuran tetap tersimpan.');
    }
}
