<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\MonitoringExcelImport;
use App\Services\Pumk\PumkActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MonitoringUploadController extends Controller
{
    public function index(): View
    {
        return view('pumk-admin.monitoring.upload');
    }

    public function store(Request $request, MonitoringExcelImport $importer): RedirectResponse
    {
        $request->validate([
            'monitoring_file' => [
                'required',
                'file',
                'max:10240',
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
            ],
        ], [
            'monitoring_file.required' => 'Pilih file Excel yang akan diunggah.',
            'monitoring_file.max' => 'Ukuran file Excel maksimal 10 MB.',
            'monitoring_file.extensions' => 'File monitoring wajib berformat .xlsx.',
            'monitoring_file.mimetypes' => 'Isi file tidak dikenali sebagai workbook .xlsx.',
        ]);

        $file = $request->file('monitoring_file');
        $path = $file?->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors([
                'monitoring_file' => 'File unggahan sementara tidak dapat dibaca. Silakan unggah kembali.',
            ]);
        }

        try {
            $summary = $importer->import($path);
        } catch (RuntimeException $exception) {
            return back()
                ->withErrors(['monitoring_file' => $exception->getMessage()])
                ->withInput();
        }

        app(PumkActivityLogger::class)->record(
            'upload_monitoring_workbook',
            'pumk_bri',
            'Mengunggah workbook data monitoring.',
            metadata: ['sheet_count' => count($summary)],
        );

        return back()
            ->with('success', 'File monitoring selesai diproses. Periksa hasil setiap sheet di bawah ini.')
            ->with('import_summary', $summary);
    }
}
