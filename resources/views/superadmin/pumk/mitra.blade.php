<x-layouts.admin title="Mitra PUMK - Pemantauan">
    @include('superadmin.pumk.styles')
    <div class="pumk-monitor">
        <h1>Daftar Mitra PUMK Internal</h1>
        <p class="intro">Tampilan baca-saja. Identitas sensitif seperti KTP, telepon, rekening, dan alamat tidak ditampilkan.</p>
        <div class="actions"><a class="button secondary" href="{{ route('superadmin.pumk.index', ['tab' => 'internal']) }}">Kembali ke Ringkasan</a></div>
        <nav class="status-tabs" aria-label="Status Mitra">
            @foreach(['aktif' => 'Aktif', 'lunas' => 'Selesai / Arsip', 'semua' => 'Semua'] as $value => $label)
                <a @class(['active' => $statusFilter === $value]) href="{{ route('superadmin.pumk.mitra', ['status' => $value, 'q' => request('q')]) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('superadmin.pumk.mitra') }}" class="panel search">
            <input type="hidden" name="status" value="{{ $statusFilter }}">
            <label for="pumk-monitor-q" class="sr-only">Cari nama mitra</label>
            <input id="pumk-monitor-q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Cari nama mitra">
            <button class="button" type="submit">Cari</button>
        </form>
        <section class="panel">
            <div class="table-wrap"><table><thead><tr><th>Nama Mitra</th><th>Wilayah</th><th>Sektor</th><th>Status</th><th>Kolektibilitas</th><th class="number">Sisa Pinjaman</th><th>Kartu</th></tr></thead><tbody>
                @forelse($mitraList as $mitra)
                    @php($pinjaman = $mitra->pinjaman->first())
                    <tr>
                        <td>{{ $mitra->nama_mitra }}</td>
                        <td>{{ $mitra->wilayah?->nama ?? $mitra->wilayah_sumber ?? '-' }}</td>
                        <td>{{ $mitra->sektorUsaha?->nama ?? $mitra->sektor_sumber ?? '-' }}</td>
                        <td><span class="status-badge {{ $mitra->pinjaman_aktif_exists ? 'aktif' : 'lunas' }}">{{ $mitra->pinjaman_aktif_exists ? 'Aktif' : 'Selesai / Arsip' }}</span></td>
                        <td>{{ $pinjaman?->kolektibilitas ? ucwords(str_replace('_', ' ', $pinjaman->kolektibilitas)) : '-' }}</td>
                        <td class="number">{{ $pinjaman?->total_sisa !== null ? 'Rp '.number_format((float) $pinjaman->total_sisa, 0, ',', '.') : '-' }}</td>
                        <td>@if($pinjaman)<a href="{{ route('superadmin.pumk.kartu', [$mitra, 'pinjaman' => $pinjaman->id]) }}">Lihat</a>@else - @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Tidak ada mitra yang sesuai.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="pages">{{ $mitraList->links() }}</div>
        </section>
    </div>
</x-layouts.admin>
