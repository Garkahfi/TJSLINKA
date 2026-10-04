<?php

namespace App\Http\Controllers;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Services\Monitoring\PumkClassificationService;
use App\Services\Pumk\KartuPiutangService;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkActivityLogger;
use App\Services\Pumk\PumkCardExports;
use App\Services\Pumk\PumkCollectibilitySummaryService;
use App\Services\Pumk\PumkDocumentResponses;
use App\Services\Pumk\PumkInstallmentChanges;
use App\Services\Pumk\PumkLoanDocumentService;
use App\Services\Pumk\PumkLoanSettlementService;
use App\Services\Pumk\PumkMitraChanges;
use App\Services\Pumk\PumkMitraOwnership;
use App\Services\Pumk\PumkMitraPages;
use App\Services\Pumk\PumkPaymentProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkMitraController extends Controller
{
    public function index(Request $request, PumkCollectibilitySummaryService $collectibilitySummary): View
    {
        return app(PumkMitraPages::class)->index($request, $collectibilitySummary);
    }

    public function create(): View
    {
        return app(PumkMitraPages::class)->create();
    }

    public function store(
        Request $request,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
    ): RedirectResponse {
        return app(PumkMitraChanges::class)->store($request, $calculator, $activity, $documents, $classifications);
    }

    public function show(Request $request, PumkMitra $mitra, KartuPiutangService $kartuPiutang, PumkLoanSettlementService $settlement): View
    {
        return app(PumkMitraPages::class)->show($request, $mitra, $kartuPiutang, $settlement);
    }

    public function edit(Request $request, PumkMitra $mitra): View
    {
        return app(PumkMitraPages::class)->edit($request, $mitra);
    }

    public function update(
        Request $request,
        PumkMitra $mitra,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
    ): RedirectResponse {
        return app(PumkMitraChanges::class)->update($request, $mitra, $calculator, $activity, $documents, $classifications);
    }

    public function reopen(Request $request, PumkMitra $mitra, PumkPinjaman $pinjaman, PumkLoanSettlementService $settlement): RedirectResponse
    {
        PumkMitraOwnership::loan($mitra, $pinjaman);
        $data = $request->validate(['reopen_note' => ['required', 'string', 'min:5', 'max:1000']]);
        $settlement->reopen($mitra->id, $pinjaman->id, $data['reopen_note'], (int) auth('pumk')->id());

        return redirect()->route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman->id])
            ->with('success', 'Pinjaman lama dibuka kembali dengan saldo dan histori yang sama. Tidak ada pinjaman baru.');
    }

    public function storeAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        return app(PumkInstallmentChanges::class)->storeAngsuran($request, $mitra, $pinjaman, $calculator, $proofs);
    }

    public function updateAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        return app(PumkInstallmentChanges::class)->updateAngsuran($request, $mitra, $pinjaman, $angsuran, $calculator, $proofs);
    }

    public function markPaid(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkLoanSettlementService $settlement,
    ): RedirectResponse {
        PumkMitraOwnership::loan($mitra, $pinjaman);
        $data = $request->validate([
            'lunas_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $result = $settlement->settle(
            $mitra->id, $pinjaman->id, $data['lunas_note'] ?? null,
            (int) auth('pumk')->id(),
        );

        return redirect()->route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman->id])
            ->with('success', $result['status'] === 'already_paid'
                ? 'Pinjaman sudah berstatus lunas; catatan penutupan pertama tetap tersimpan.'
                : 'Pinjaman berhasil ditandai lunas. Seluruh histori tetap tersimpan.');
    }

    public function exportExcel(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        return app(PumkCardExports::class)->exportExcel($request, $mitra, $pinjaman, $kartuPiutang);
    }

    public function exportPdf(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        return app(PumkCardExports::class)->exportPdf($request, $mitra, $pinjaman, $kartuPiutang);
    }

    public function viewDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        return app(PumkDocumentResponses::class)->viewDocument($mitra, $pinjaman, $document);
    }

    public function downloadDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        return app(PumkDocumentResponses::class)->downloadDocument($mitra, $pinjaman, $document);
    }

    public function destroyDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
        PumkLoanDocumentService $documents,
    ): RedirectResponse {
        return app(PumkDocumentResponses::class)->destroyDocument($mitra, $pinjaman, $document, $documents);
    }

    public function viewPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        return app(PumkDocumentResponses::class)->viewPaymentProof($mitra, $pinjaman, $angsuran);
    }

    public function downloadPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        return app(PumkDocumentResponses::class)->downloadPaymentProof($mitra, $pinjaman, $angsuran);
    }

    public function destroyPaymentProof(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        return app(PumkDocumentResponses::class)->destroyPaymentProof($mitra, $pinjaman, $angsuran, $proofs);
    }
}
