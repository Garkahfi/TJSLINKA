<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PumkCardExports
{
    public function exportExcel(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        $data = $this->kartuExportData($mitra, $pinjaman, $kartuPiutang, PumkYearFilter::requestedYear($request) ?? 'terbaru');
        $filename = 'kartu-piutang-'.Str::slug($mitra->nama_mitra).'-'.PumkYearFilter::yearFileLabel($data['kartu']['tahun_terpilih']).'.xls';

        return response()
            ->view('pumk-admin.mitra.exports.excel', $data)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function exportPdf(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        $data = $this->kartuExportData($mitra, $pinjaman, $kartuPiutang, PumkYearFilter::requestedYear($request) ?? 'terbaru');
        $logoPath = public_path('images/logo/inka.png');
        $data['logoDataUri'] = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pumk-admin.mitra.exports.pdf', $data)->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        $filename = 'kartu-piutang-'.Str::slug($mitra->nama_mitra).'-'.PumkYearFilter::yearFileLabel($data['kartu']['tahun_terpilih']).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function kartuExportData(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
        int|string|null $tahun = null,
    ): array {
        PumkMitraOwnership::loan($mitra, $pinjaman);
        $mitra->loadMissing(['wilayah:id,nama', 'sektorUsaha:id,nama']);
        $pinjaman->load([
            'saldoAwal',
            'angsuran' => fn ($query) => $query->oldest('periode'),
        ]);

        return [
            'mitra' => $mitra,
            'pinjaman' => $pinjaman,
            'kartu' => $kartuPiutang->buat($pinjaman, tahun: $tahun),
            'generatedAt' => now(),
        ];
    }
}
