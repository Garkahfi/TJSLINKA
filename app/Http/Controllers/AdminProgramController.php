<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramDocument;
use App\Services\AdminTjsl\ProgramChanges;
use App\Services\AdminTjsl\ProgramDocuments;
use App\Services\AdminTjsl\ProgramPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProgramController extends Controller
{
    public function __construct(
        private readonly ProgramPages $pages,
        private readonly ProgramChanges $changes,
        private readonly ProgramDocuments $documents,
    ) {}

    public function index(Request $request): View
    {
        return $this->pages->index($request);
    }

    public function create(): View
    {
        return $this->pages->create();
    }

    public function createCooperationForm(string $jenisKerjasama): View
    {
        return $this->pages->createCooperationForm($jenisKerjasama);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->changes->store($request);
    }

    public function show(Request $request, Program $program): View
    {
        return $this->pages->show($request, $program);
    }

    public function phaseTwo(Request $request, Program $program): View
    {
        return $this->pages->phaseTwo($request, $program);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        return $this->changes->update($request, $program);
    }

    public function cancel(Request $request, Program $program): RedirectResponse
    {
        return $this->changes->cancel($request, $program);
    }

    public function uploadBast(Request $request, Program $program): RedirectResponse
    {
        return $this->documents->uploadBast($request, $program);
    }

    public function downloadDocument(Request $request, ProgramDocument $document)
    {
        return $this->documents->downloadDocument($request, $document);
    }

    public function destroyDocument(Request $request, ProgramDocument $document): RedirectResponse
    {
        return $this->documents->destroyDocument($request, $document);
    }
}
