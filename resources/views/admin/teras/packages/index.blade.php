<x-layouts.admin title="Kelola Paket Teras TJSL">
    <div class="mx-auto max-w-6xl">
        <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Paket Teras TJSL</h1>
                <p class="mt-1 text-sm text-slate-500">Paket aktif langsung tampil di halaman publik Teras TJSL.</p>
            </div>
            <a href="{{ route('admin.teras.packages.create') }}" class="rounded-lg bg-action-primary px-5 py-3 font-semibold text-white hover:bg-action-primary-hover">
                + Tambah Paket
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
                        <th class="px-5 py-4">Nama Paket</th>
                        <th class="px-5 py-4">Harga</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($packages as $package)
                        <tr>
                            <td class="px-5 py-3">
                                @if ($package->photo_url)
                                    <img src="{{ $package->photo_url }}" alt="{{ $package->nama_paket }}" class="h-14 w-14 rounded object-cover">
                                @else
                                    <div class="grid h-14 w-14 place-items-center rounded bg-slate-100 text-xs text-slate-400">Tidak ada foto</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-semibold text-slate-900">{{ $package->nama_paket }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $package->formatted_harga }}</td>
                            <td class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.teras.packages.toggle', $package) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-full px-3 py-1 text-xs font-semibold {{ $package->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $package->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.teras.packages.edit', $package) }}" class="rounded border border-action-primary px-3 py-1.5 text-xs font-semibold text-action-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.teras.packages.destroy', $package) }}" onsubmit="return confirm('Hapus paket ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada paket Teras TJSL.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $packages->links() }}</div>
    </div>
</x-layouts.admin>
