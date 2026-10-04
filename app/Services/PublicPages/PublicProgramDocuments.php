<?php

namespace App\Services\PublicPages;

use App\Models\BantuanCsr;
use App\Models\BantuanCsrDocument;
use App\Models\Program;
use App\Models\ProgramDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicProgramDocuments
{
    public function viewCsrDocument(
        BantuanCsr $bantuanCsr,
        BantuanCsrDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicCsrDocument($bantuanCsr, $document);

        return response()->file(
            Storage::disk('local')->path($document->file_path),
            ['Content-Disposition' => 'inline; filename="'.$this->csrDocumentFilename($document).'"'],
        );
    }

    public function downloadCsrDocument(
        BantuanCsr $bantuanCsr,
        BantuanCsrDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicCsrDocument($bantuanCsr, $document);

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $this->csrDocumentFilename($document),
        );
    }

    public function viewProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicProgramDocument($program, $document);

        return response()->file(
            Storage::disk('local')->path($document->file_path),
            ['Content-Disposition' => 'inline; filename="'.$this->programDocumentFilename($document).'"'],
        );
    }

    public function downloadProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): BinaryFileResponse {
        $this->authorizePublicProgramDocument($program, $document);

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $this->programDocumentFilename($document),
        );
    }

    private function authorizePublicCsrDocument(
        BantuanCsr $program,
        BantuanCsrDocument $document,
    ): void {
        abort_unless(
            $document->bantuan_csr_id === $program->id
                && in_array($program->status, BantuanCsr::OVERVIEW_STATUSES, true)
                && ! $program->is_archived
                && Storage::disk('local')->exists($document->file_path),
            404,
        );
    }

    private function authorizePublicProgramDocument(
        Program $program,
        ProgramDocument $document,
    ): void {
        abort_unless(
            $document->program_id === $program->id
                && in_array($program->status, Program::OVERVIEW_STATUSES, true)
                && ! $program->is_archived
                && Storage::disk('local')->exists($document->file_path),
            404,
        );
    }

    private function programDocumentFilename(ProgramDocument $document): string
    {
        $name = trim(str_replace(['/', '\\', '"'], '-', $document->nama_dokumen));
        $name = $name !== '' ? $name : basename($document->file_path);
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$extension;
        }

        return $name;
    }

    private function csrDocumentFilename(BantuanCsrDocument $document): string
    {
        $name = trim(str_replace(['/', '\\', '"'], '-', $document->nama_dokumen));
        $name = $name !== '' ? $name : basename($document->file_path);
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$extension;
        }

        return $name;
    }
}
