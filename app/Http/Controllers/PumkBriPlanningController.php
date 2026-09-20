<?php

namespace App\Http\Controllers;

use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\PumkBriSnapshotBulanan;
use App\Services\Pumk\PumkActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PumkBriPlanningController extends Controller
{
    public function index(Request $request): View
    {
        $years = PumkBriSnapshotBulanan::query()->distinct()->pluck('tahun')
            ->merge(PumkBriRkaTahunan::query()->pluck('tahun'))
            ->merge(PumkBriPenyaluranBulanan::query()->pluck('tahun'))
            ->push(now()->year)
            ->map(fn (mixed $year): int => (int) $year)->unique()->sortDesc()->values();
        $year = (int) $request->integer('tahun', (int) ($years->first() ?? now()->year));
        abort_unless($year >= 1900 && $year <= 2100, 422, 'Tahun tidak valid.');

        $rka = PumkBriRkaTahunan::query()->where('tahun', $year)->first();
        $monthly = PumkBriPenyaluranBulanan::query()->where('tahun', $year)->orderBy('bulan')->get()->keyBy('bulan');
        $actual = (float) $monthly->sum('nominal_penyaluran');

        return view('pumk-admin.bri-planning.index', [
            'years' => $years,
            'year' => $year,
            'rka' => $rka,
            'monthly' => $monthly,
            'actual' => $actual,
            'progress' => $rka !== null && (float) $rka->nominal_rka > 0
                ? round($actual / (float) $rka->nominal_rka * 100, 2)
                : null,
        ]);
    }

    public function storeRka(Request $request): RedirectResponse
    {
        $data = $request->validate(['tahun' => ['required', 'integer', 'between:1900,2100'], 'nominal_rka' => ['required', 'string']]);
        $nominal = $this->rupiah($data['nominal_rka']);
        if ($nominal <= 0) {
            return back()->withErrors(['nominal_rka' => 'RKA harus lebih besar dari 0.'])->withInput();
        }

        $userId = auth('pumk')->id();
        $record = PumkBriRkaTahunan::query()->firstOrNew(['tahun' => (int) $data['tahun']]);
        if (! $record->exists) {
            $record->created_by = $userId;
        }
        $record->fill(['nominal_rka' => $nominal, 'updated_by' => $userId])->save();
        app(PumkActivityLogger::class)->record(
            'update_bri_rka',
            'pumk_bri',
            'Menyimpan RKA tahunan PUMK BRI.',
            $record,
            metadata: ['tahun' => (int) $data['tahun']],
        );

        return redirect()->route('pumk-admin.bri-planning.index', ['tahun' => $data['tahun']])
            ->with('success', 'RKA tahunan berhasil disimpan.');
    }

    public function storeMonthly(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'between:1900,2100'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'nominal_penyaluran' => ['required', 'string'],
        ]);
        $nominal = $this->rupiah($data['nominal_penyaluran']);
        if ($nominal < 0) {
            return back()->withErrors(['nominal_penyaluran' => 'Nilai penyaluran tidak boleh negatif.'])->withInput();
        }

        $userId = auth('pumk')->id();
        $record = PumkBriPenyaluranBulanan::query()->firstOrNew([
            'tahun' => (int) $data['tahun'], 'bulan' => (int) $data['bulan'],
        ]);
        if (! $record->exists) {
            $record->created_by = $userId;
        }
        $record->fill(['nominal_penyaluran' => $nominal, 'updated_by' => $userId])->save();
        app(PumkActivityLogger::class)->record(
            'update_bri_realization',
            'pumk_bri',
            'Menyimpan realisasi bulanan PUMK BRI.',
            $record,
            metadata: ['tahun' => (int) $data['tahun'], 'bulan' => (int) $data['bulan']],
        );

        return redirect()->route('pumk-admin.bri-planning.index', ['tahun' => $data['tahun']])
            ->with('success', 'Realisasi bulanan berhasil disimpan.');
    }

    public function destroyMonthly(PumkBriPenyaluranBulanan $penyaluran): RedirectResponse
    {
        $year = $penyaluran->tahun;
        $month = $penyaluran->bulan;
        app(PumkActivityLogger::class)->record(
            'delete_bri_realization',
            'pumk_bri',
            'Mengosongkan realisasi bulanan PUMK BRI.',
            $penyaluran,
            metadata: ['tahun' => (int) $year, 'bulan' => (int) $month],
        );
        $penyaluran->delete();

        return redirect()->route('pumk-admin.bri-planning.index', ['tahun' => $year])
            ->with('success', 'Realisasi bulan dikembalikan ke status belum diinput.');
    }

    private function rupiah(string $value): float
    {
        $normalized = preg_replace('/[^0-9-]/', '', $value) ?? '';
        if ($normalized === '' || ! preg_match('/^-?\d+$/', $normalized)) {
            abort(422, 'Nominal tidak valid.');
        }

        return (float) $normalized;
    }
}
