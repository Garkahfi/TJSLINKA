<?php

namespace App\Http\Controllers;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Services\AdminTjsl\BantuanCsrChanges;
use App\Services\AdminTjsl\BantuanCsrDocuments;
use App\Services\AdminTjsl\BantuanCsrPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBantuanCsrController extends Controller
{
    public function __construct(
        private readonly BantuanCsrPages $pages,
        private readonly BantuanCsrChanges $changes,
        private readonly BantuanCsrDocuments $documents,
    ) {}

    public function index(Request $request): View
    {
        return $this->pages->index($request);
    }

    public function create(): View
    {
        return $this->pages->create();
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->changes->store($request);
    }

    public function show(Request $request, BantuanCsr $bantuanCsr): View
    {
        return $this->pages->show($request, $bantuanCsr);
    }

    public function phaseTwo(Request $request, BantuanCsr $bantuanCsr): View
    {
        return $this->pages->phaseTwo($request, $bantuanCsr);
    }

    public function update(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        return $this->changes->update($request, $bantuanCsr);
    }

    public function cancel(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        return $this->changes->cancel($request, $bantuanCsr);
    }

    public function uploadBast(Request $request, BantuanCsr $bantuanCsr): RedirectResponse
    {
        return $this->documents->uploadBast($request, $bantuanCsr);
    }

    public function downloadDocument(Request $request, BantuanCsrDocument $document)
    {
        return $this->documents->downloadDocument($request, $document);
    }

    public function destroyDocument(
        Request $request,
        BantuanCsrDocument $document,
    ): RedirectResponse {
        return $this->documents->destroyDocument($request, $document);
    }
}
