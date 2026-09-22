<x-layouts.admin title="Kelola Produk Teras TJSL">
    <div class="mx-auto max-w-6xl">
        <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Produk Teras TJSL</h1>
                <p class="mt-1 text-sm text-slate-500">Produk aktif langsung tampil di halaman publik Teras TJSL.</p>
            </div>
            <a href="{{ route('admin.teras.products.create') }}" class="rounded-lg bg-action-primary px-5 py-3 font-semibold text-white hover:bg-action-primary-hover">
                + Tambah Produk
            </a>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-slate-700">
                    <tr>
                        <th class="px-5 py-4">Foto</th>
                        <th class="px-5 py-4">Nama Produk</th>
                        <th class="px-5 py-4">Nama UMKM</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-5 py-3">
                                @if ($product->photo_url)
                                    <img src="{{ $product->photo_url }}" alt="{{ $product->nama_produk }}" class="h-14 w-14 rounded object-cover">
                                @else
                                    <div class="grid h-14 w-14 place-items-center rounded bg-slate-100 text-xs text-slate-400">Tidak ada foto</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-semibold text-slate-900">{{ $product->nama_produk }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $product->nama_umkm }}</td>
                            <td class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.teras.products.toggle', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-full px-3 py-1 text-xs font-semibold {{ $product->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.teras.products.edit', $product) }}" class="rounded border border-action-primary px-3 py-1.5 text-xs font-semibold text-action-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.teras.products.destroy', $product) }}" onsubmit="return confirm('Hapus produk ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada produk Teras TJSL.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $products->links() }}</div>
    </div>
</x-layouts.admin>
