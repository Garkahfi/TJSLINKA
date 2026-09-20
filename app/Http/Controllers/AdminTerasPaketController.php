<?php

namespace App\Http\Controllers;

use App\Models\TerasPaket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminTerasPaketController extends Controller
{
    public function index(): View
    {
        return view('admin.teras.packages.index', [
            'packages' => TerasPaket::query()
                ->orderBy('urutan')
                ->orderBy('id')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        $package = new TerasPaket;
        $package->fill([
            'tipe_harga' => 'tetap',
            'is_active' => true,
            'isi_paket' => [''],
        ]);

        return view('admin.teras.packages.form', [
            'package' => $package,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $data['foto_path'] = $this->storePhoto($request);
        $data['urutan'] = (int) TerasPaket::max('urutan') + 1;
        $data['created_by'] = $request->user()->id;

        TerasPaket::create($data);

        return redirect()
            ->route('admin.teras.packages.index')
            ->with('success', 'Paket Teras TJSL berhasil disimpan dan langsung tayang bila aktif.');
    }

    public function edit(TerasPaket $terasPaket): View
    {
        return view('admin.teras.packages.form', ['package' => $terasPaket]);
    }

    public function update(Request $request, TerasPaket $terasPaket): RedirectResponse
    {
        $data = $this->data($request);

        if ($request->hasFile('foto')) {
            $oldPhotoPath = $terasPaket->foto_path;
            $data['foto_path'] = $this->storePhoto($request);
        }

        $terasPaket->update($data);

        if (isset($oldPhotoPath)) {
            $this->deleteStoredPhoto($oldPhotoPath);
        }

        return redirect()
            ->route('admin.teras.packages.index')
            ->with('success', 'Paket Teras TJSL berhasil diperbarui.');
    }

    public function destroy(TerasPaket $terasPaket): RedirectResponse
    {
        $this->deleteStoredPhoto($terasPaket->foto_path);
        $terasPaket->delete();

        return back()->with('success', 'Paket Teras TJSL telah dihapus.');
    }

    public function toggle(TerasPaket $terasPaket): RedirectResponse
    {
        $terasPaket->update(['is_active' => ! $terasPaket->is_active]);

        return back()->with('success', 'Status tayang paket diperbarui.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'nama_paket' => ['required', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'max:20480'],
            'harga' => ['required', 'numeric', 'min:0'],
            'tipe_harga' => ['required', 'in:tetap,maksimal'],
            'isi_paket' => ['required', 'array', 'min:1'],
            'isi_paket.*' => ['required', 'string', 'max:255'],
            'catatan_khusus' => ['nullable', 'string'],
        ]);

        $data['isi_paket'] = array_values($data['isi_paket']);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['foto']);

        return $data;
    }

    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('foto')
            ? $request->file('foto')->store('teras/paket', 'public')
            : null;
    }

    private function deleteStoredPhoto(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'images/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
