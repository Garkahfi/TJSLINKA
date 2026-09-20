<x-layouts.admin :title="$product->exists ? 'Edit Produk Teras TJSL' : 'Tambah Produk Teras TJSL'">
    <div class="mx-auto max-w-3xl">
        <div class="mb-7 flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold">{{ $product->exists ? 'Edit Produk' : 'Tambah Produk' }}</h1>
            <a href="{{ route('admin.teras.products.index') }}" class="text-sm font-semibold text-slate-600">Kembali ke Produk</a>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.teras.products.update', $product) : route('admin.teras.products.store') }}" class="space-y-5 rounded-xl bg-white p-6 shadow">
            @csrf
            @if ($product->exists) @method('PUT') @endif

            <label class="block">
                <span class="mb-2 block font-semibold">Nama Produk</span>
                <input name="nama_produk" value="{{ old('nama_produk', $product->nama_produk) }}" required class="w-full rounded-lg border px-3 py-2.5">
                @error('nama_produk') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block font-semibold">Nama UMKM</span>
                <input name="nama_umkm" value="{{ old('nama_umkm', $product->nama_umkm) }}" required class="w-full rounded-lg border px-3 py-2.5">
                @error('nama_umkm') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <div>
                <span class="mb-2 block font-semibold">Foto Produk</span>
                @if ($product->photo_url)
                    <img src="{{ $product->photo_url }}" alt="Foto produk saat ini" class="mb-3 h-28 w-28 rounded-lg object-cover">
                @endif
                <input type="file" name="foto" accept="image/*" class="block w-full rounded-lg border p-2">
                <small class="text-slate-500">Opsional. Maksimal 20 MB.</small>
                @error('foto') <small class="block text-red-600">{{ $message }}</small> @enderror
            </div>

            <label class="block">
                <span class="mb-2 block font-semibold">Deskripsi <em class="font-normal text-slate-500">(opsional)</em></span>
                <textarea name="deskripsi" rows="4" class="w-full rounded-lg border px-3 py-2.5">{{ old('deskripsi', $product->deskripsi) }}</textarea>
                @error('deskripsi') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="flex items-center gap-3 rounded-lg bg-slate-50 p-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="h-5 w-5">
                <span><strong>Aktif / tampil di publik</strong><br><small class="text-slate-500">Nonaktifkan jika produk sedang tidak tersedia tanpa menghapus data.</small></span>
            </label>

            <button class="w-full rounded-lg bg-[#2653ff] px-5 py-3 font-semibold text-white hover:bg-[#1f46dc]">Simpan</button>
        </form>
    </div>
</x-layouts.admin>
