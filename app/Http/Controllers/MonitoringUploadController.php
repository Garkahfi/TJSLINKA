<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\MonitoringExcelImport;
use App\Services\Pumk\PumkActivityLogger;
use App\Services\Pumk\PumkBriSnapshotImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MonitoringUploadController extends Controller
{
    public function index(): View
    {
        return view('pumk-admin.monitoring.upload');
    }

    public function downloadBriTemplate(): BinaryFileResponse
    {
        return response()->download(
            resource_path('templates/pumk-bri-snapshot.xlsx'),
            'template-snapshot-pumk-bri.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function store(
        Request $request,
        MonitoringExcelImport $tjslImporter,
        PumkBriSnapshotImportService $briImporter,
        PumkActivityLogger $activityLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'import_type' => ['required', Rule::in(['tjsl', 'pumk_bri_snapshot'])],
            'default_year' => [Rule::excludeIf($request->input('import_type') !== 'pumk_bri_snapshot'), 'required', 'integer', 'between:1900,2100'],
            'monitoring_file' => [
                'required',
                'file',
                'max:10240',
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
            ],
        ], [
            'import_type.required' => 'Pilih jenis data yang akan diunggah.',
            'import_type.in' => 'Jenis data tidak dikenali.',
            'default_year.required' => 'Tahun default wajib diisi untuk Snapshot PUMK BRI.',
            'default_year.integer' => 'Tahun default harus berupa angka bulat.',
            'default_year.between' => 'Tahun default harus berada pada rentang 1900–2100.',
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
            $summary = $validated['import_type'] === 'tjsl'
                ? $tjslImporter->importTjsl($path)
                : $briImporter->importForWebUpload($path, (int) $validated['default_year']);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['monitoring_file' => $exception->getMessage()])
                ->withInput();
        } catch (RuntimeException $exception) {
            Log::error('Workbook monitoring tidak dapat dibaca.', ['exception_type' => $exception::class]);

            return back()
                ->withErrors(['monitoring_file' => 'Workbook XLSX tidak dapat dibaca atau strukturnya tidak sesuai.'])
                ->withInput();
        } catch (Throwable $exception) {
            Log::error('Upload monitoring gagal diproses.', ['exception_type' => $exception::class]);

            return back()
                ->withErrors(['monitoring_file' => 'File belum dapat diproses. Periksa log aplikasi atau hubungi pengelola.'])
                ->withInput();
        }

        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $needsReview = 0;
        foreach ($summary as $result) {
            $status = $result['status'];
            $successful += (int) ($status === 'imported'
                || (in_array($status, ['success', 'partial'], true) && $result['berhasil'] > 0));
            $failed += (int) in_array($status, ['failed', 'partial', 'needs_review'], true);
            $skipped += (int) in_array($status, ['skipped', 'ignored'], true);
            $needsReview += (int) ($status === 'needs_review');
        }

        $level = $successful > 0
            ? ($failed > 0 ? 'warning' : 'success')
            : ($failed > 0 ? 'error' : 'info');
        $message = match ($level) {
            'success' => 'Data berhasil diproses. Periksa rincian setiap sheet.',
            'warning' => 'Sebagian data berhasil diproses; sebagian gagal atau menunggu review. Periksa rincian setiap sheet.',
            'error' => $needsReview > 0
                ? 'Snapshot belum selesai diimpor karena ada identitas yang perlu direview. Periksa rincian setiap sheet.'
                : 'Tidak ada data yang berhasil diimpor. Periksa rincian setiap sheet.',
            default => $validated['import_type'] === 'tjsl'
                ? 'Tidak ada baris baru yang diimpor; data lama tetap digunakan.'
                : 'Tidak ada data baru yang diimpor. Periode yang sudah ada tetap digunakan.',
        };

        $activityLogger->record(
            'upload_monitoring_workbook',
            $validated['import_type'] === 'tjsl' ? 'tjsl' : 'pumk_bri',
            'Mengunggah workbook data monitoring.',
            metadata: [
                'import_type' => $validated['import_type'],
                'result' => $level,
                'successful_units' => $successful,
                'failed_units' => $failed,
                'skipped_units' => $skipped,
                'needs_review_units' => $needsReview,
            ],
        );

        return back()
            ->with('import_result', [
                'type' => $validated['import_type'],
                'level' => $level,
                'message' => $message,
                'summary' => $summary,
            ]);
    }
}
