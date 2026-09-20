<?php

namespace App\Http\Controllers;

use App\Models\TerasProduk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminTerasProdukController extends Controller
{
    public function index(): View
    {
        return view('admin.teras.products.index', [
            'products' => TerasProduk::query()->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        $product = new TerasProduk;
        $product->fill(['is_active' => true]);

        return view('admin.teras.products.form', [
            'product' => $product,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $data['foto_path'] = $this->storePhoto($request);
        $data['created_by'] = $request->user()->id;

        TerasProduk::create($data);

        return redirect()
            ->route('admin.teras.products.index')
            ->with('success', 'Produk Teras TJSL berhasil disimpan dan langsung tayang bila aktif.');
    }

    public function edit(TerasProduk $terasProduk): View
    {
        return view('admin.teras.products.form', ['product' => $terasProduk]);
    }

    public function update(Request $request, TerasProduk $terasProduk): RedirectResponse
    {
        $data = $this->data($request);

        if ($request->hasFile('foto')) {
            $oldPhotoPath = $terasProduk->foto_path;
            $data['foto_path'] = $this->storePhoto($request);
        }

        $terasProduk->update($data);

        if (isset($oldPhotoPath)) {
            $this->deleteStoredPhoto($oldPhotoPath);
        }

        return redirect()
            ->route('admin.teras.products.index')
            ->with('success', 'Produk Teras TJSL berhasil diperbarui.');
    }

    public function destroy(TerasProduk $terasProduk): RedirectResponse
    {
        $this->deleteStoredPhoto($terasProduk->foto_path);
        $terasProduk->delete();

        return back()->with('success', 'Produk Teras TJSL telah dihapus.');
    }

    public function toggle(TerasProduk $terasProduk): RedirectResponse
    {
        $terasProduk->update(['is_active' => ! $terasProduk->is_active]);

        return back()->with('success', 'Status tayang produk diperbarui.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'nama_produk' => ['required', 'string', 'max:255'],
            'nama_umkm' => ['required', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'max:20480'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        unset($data['foto']);

        return $data;
    }

    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('foto')
            ? $request->file('foto')->store('teras/produk', 'public')
            : null;
    }

    private function deleteStoredPhoto(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'images/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
