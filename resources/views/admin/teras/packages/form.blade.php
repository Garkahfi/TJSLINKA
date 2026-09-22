<x-layouts.admin :title="$package->exists ? 'Edit Paket Teras TJSL' : 'Tambah Paket Teras TJSL'">
    @php($items = old('isi_paket', $package->isi_paket ?: ['']))

    <div class="mx-auto max-w-3xl">
        <div class="mb-7 flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold">{{ $package->exists ? 'Edit Paket' : 'Tambah Paket' }}</h1>
            <a href="{{ route('admin.teras.packages.index') }}" class="text-sm font-semibold text-slate-600">Kembali ke Paket</a>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ $package->exists ? route('admin.teras.packages.update', $package) : route('admin.teras.packages.store') }}" class="space-y-5 rounded-xl bg-white p-6 shadow">
            @csrf
            @if ($package->exists) @method('PUT') @endif

            <label class="block">
                <span class="mb-2 block font-semibold">Nama Paket</span>
                <input name="nama_paket" value="{{ old('nama_paket', $package->nama_paket) }}" required class="w-full rounded-lg border px-3 py-2.5">
                @error('nama_paket') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <div>
                <span class="mb-2 block font-semibold">Foto Paket</span>
                @if ($package->photo_url)
                    <img src="{{ $package->photo_url }}" alt="Foto paket saat ini" class="mb-3 h-28 w-28 rounded-lg object-cover">
                @endif
                <input type="file" name="foto" accept="image/*" class="block w-full rounded-lg border p-2">
                <small class="text-slate-500">Opsional. Maksimal 20 MB.</small>
                @error('foto') <small class="block text-red-600">{{ $message }}</small> @enderror
            </div>

            <label class="block">
                <span class="mb-2 block font-semibold">Harga</span>
                <input type="number" min="0" step="0.01" name="harga" value="{{ old('harga', $package->harga) }}" required class="w-full rounded-lg border px-3 py-2.5" placeholder="Contoh: 800000">
                @error('harga') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <fieldset>
                <legend class="mb-2 font-semibold">Tipe Harga</legend>
                <div class="flex flex-wrap gap-5">
                    <label class="flex items-center gap-2"><input type="radio" name="tipe_harga" value="tetap" @checked(old('tipe_harga', $package->tipe_harga) === 'tetap')> Harga Tetap</label>
                    <label class="flex items-center gap-2"><input type="radio" name="tipe_harga" value="maksimal" @checked(old('tipe_harga', $package->tipe_harga) === 'maksimal')> Harga Maksimal</label>
                </div>
                @error('tipe_harga') <small class="text-red-600">{{ $message }}</small> @enderror
            </fieldset>

            <fieldset>
                <legend class="mb-2 font-semibold">Isi Paket</legend>
                <div id="package-items" class="space-y-2">
                    @foreach ($items as $item)
                        <div class="flex gap-2 package-item-row">
                            <input name="isi_paket[]" value="{{ $item }}" required class="min-w-0 flex-1 rounded-lg border px-3 py-2.5" placeholder="Masukkan item paket">
                            <button type="button" class="remove-package-item rounded border border-red-300 px-3 text-red-600">Hapus</button>
                        </div>
                    @endforeach
                </div>
                <button id="add-package-item" type="button" class="mt-3 rounded-lg bg-action-primary px-4 py-2 text-sm font-semibold text-white">+ Tambah Item</button>
                @error('isi_paket') <small class="block text-red-600">{{ $message }}</small> @enderror
                @error('isi_paket.*') <small class="block text-red-600">{{ $message }}</small> @enderror
            </fieldset>

            <label class="block">
                <span class="mb-2 block font-semibold">Catatan Khusus <em class="font-normal text-slate-500">(opsional)</em></span>
                <textarea name="catatan_khusus" rows="4" class="w-full rounded-lg border px-3 py-2.5" placeholder="Contoh: Paket custom sesuai kebutuhan pemesan.">{{ old('catatan_khusus', $package->catatan_khusus) }}</textarea>
            </label>

            <label class="flex items-center gap-3 rounded-lg bg-slate-50 p-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active)) class="h-5 w-5">
                <span><strong>Aktif / tampil di publik</strong><br><small class="text-slate-500">Nonaktifkan bila paket belum tersedia tanpa menghapus data.</small></span>
            </label>

            <button class="w-full rounded-lg bg-action-primary px-5 py-3 font-semibold text-white hover:bg-action-primary-hover">Simpan</button>
        </form>
    </div>

    <script>
        (function () {
            const list = document.getElementById('package-items');
            const addButton = document.getElementById('add-package-item');

            addButton?.addEventListener('click', function () {
                const row = document.createElement('div');
                row.className = 'flex gap-2 package-item-row';
                row.innerHTML = '<input name="isi_paket[]" required class="min-w-0 flex-1 rounded-lg border px-3 py-2.5" placeholder="Masukkan item paket"><button type="button" class="remove-package-item rounded border border-red-300 px-3 text-red-600">Hapus</button>';
                list.appendChild(row);
            });

            list?.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-package-item');
                if (!button || list.children.length === 1) return;
                button.closest('.package-item-row')?.remove();
            });
        })();
    </script>
</x-layouts.admin>
