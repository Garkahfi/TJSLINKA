<x-layouts.admin title="Monitoring Admin PUMK">
    @include('superadmin.pumk.styles')
    @php($rupiah = static fn ($value) => $value === null ? 'Belum tersedia' : 'Rp '.number_format((float) $value, 0, ',', '.'))
    <div class="pumk-monitor">
        <h1>Monitoring Admin PUMK</h1>
        <p class="intro">Pantau progres PUMK Internal dan PUMK BRI tanpa mengubah pekerjaan Admin PUMK.</p>
        <p class="notice"><strong>Mode pemantau baca-saja.</strong> Super Admin dapat melihat histori dan mengunduh kartu, tetapi tidak dapat menambah, mengedit, menghapus, mengimpor, maupun menandai pinjaman lunas.</p>

        <nav class="monitor-tabs" aria-label="Jenis data PUMK">
            <a @class(['active' => $tab === 'internal']) href="{{ route('superadmin.pumk.index', ['tab' => 'internal']) }}">PUMK Internal / Kartu Piutang</a>
            <a @class(['active' => $tab === 'bri']) href="{{ route('superadmin.pumk.index', ['tab' => 'bri', 'tahun' => $year]) }}">PUMK BRI</a>
        </nav>

        @if($tab === 'internal')
            <div class="grid" aria-label="Ringkasan PUMK Internal">
                <div class="metric"><span>Mitra aktif</span><strong>{{ number_format($totalMitra, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Pinjaman aktif</span><strong>{{ number_format($totalPinjaman, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Mitra selesai / arsip</span><strong>{{ number_format($totalMitraLunas, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Pinjaman lunas</span><strong>{{ number_format($totalPinjamanLunas, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Angsuran manual</span><strong>{{ number_format($totalAngsuranManual, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Profil perlu dilengkapi</span><strong>{{ number_format($mitraBelumLengkap, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Baris perlu review</span><strong>{{ number_format($needsReview, 0, ',', '.') }}</strong></div>
            </div>
            <div class="actions"><a class="button" href="{{ route('superadmin.pumk.mitra') }}">Lihat Mitra &amp; Kartu Piutang</a></div>

            <section class="panel">
                <h2>Pinjaman lunas terbaru</h2>
                <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Mitra</th><th>Admin PUMK</th><th>Catatan</th><th>Kartu</th></tr></thead><tbody>
                    @forelse($pinjamanLunasTerbaru as $item)
                        <tr><td>{{ $item->lunas_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</td><td>{{ $item->mitra?->nama_mitra ?? '-' }}</td><td>{{ $item->pelunas?->name ?? 'Akun tidak tersedia' }}</td><td>{{ $item->lunas_note ?: '-' }}</td><td><a href="{{ route('superadmin.pumk.kartu', [$item->mitra_id, 'pinjaman' => $item->id]) }}">Lihat</a></td></tr>
                    @empty<tr><td colspan="5" class="muted">Belum ada pinjaman yang ditandai lunas.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="panel">
                <h2>Angsuran manual terbaru</h2>
                <div class="table-wrap"><table><thead><tr><th>Dicatat</th><th>Admin PUMK</th><th>Mitra</th><th>Periode</th><th class="number">Jumlah</th></tr></thead><tbody>
                    @forelse($angsuranTerbaru as $angsuran)
                        <tr><td>{{ $angsuran->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</td><td>{{ $angsuran->creator?->name ?? 'Akun tidak tersedia' }}</td><td>{{ $angsuran->pinjaman?->mitra?->nama_mitra ?? '-' }}</td><td>{{ $angsuran->periode?->format('m/Y') ?? '-' }}</td><td class="number">{{ $rupiah($angsuran->total) }}</td></tr>
                    @empty<tr><td colspan="5" class="muted">Belum ada angsuran manual.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="panel">
                <h2>Aktivitas Admin PUMK terbaru</h2>
                <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Admin</th><th>Modul</th><th>Aktivitas</th></tr></thead><tbody>
                    @forelse($aktivitasTerbaru as $activity)
                        <tr><td>{{ $activity->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td>{{ $activity->actor?->name ?? 'Sistem' }}</td><td>{{ $activity->module }}</td><td>{{ $activity->description_safe }}</td></tr>
                    @empty<tr><td colspan="4" class="muted">Belum ada aktivitas yang tercatat.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="panel">
                <h2>Batch impor kartu piutang terbaru</h2>
                <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Nama file</th><th>Pengimpor</th><th>Status</th><th>Berhasil / Gagal</th><th>Review</th></tr></thead><tbody>
                    @forelse($importTerbaru as $batch)
                        <tr><td>{{ $batch->completed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? $batch->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td>{{ $batch->nama_file }}</td><td>{{ $batch->importer?->name ?? 'Sistem' }}</td><td>{{ $batch->status }}</td><td>{{ $batch->berhasil }} / {{ $batch->gagal }}</td><td>{{ $batch->needs_review_count }}</td></tr>
                    @empty<tr><td colspan="6" class="muted">Belum ada batch impor.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @else
            <form method="GET" action="{{ route('superadmin.pumk.index') }}" class="panel year-filter">
                <input type="hidden" name="tab" value="bri"><label for="tahun">Tahun</label>
                <select id="tahun" name="tahun" onchange="this.form.submit()">
                    @forelse($years as $item)<option value="{{ $item }}" @selected($item === $year)>{{ $item }}</option>@empty<option value="{{ $year }}">{{ $year }}</option>@endforelse
                </select><noscript><button class="button" type="submit">Tampilkan</button></noscript>
            </form>
            @if($briUnverified > 0 || $briIdentityReviews > 0)
                <p class="warning">Data {{ $year }} masih memiliki {{ number_format($briUnverified, 0, ',', '.') }} snapshot belum terverifikasi dan {{ number_format($briIdentityReviews, 0, ',', '.') }} kasus identitas menunggu review.</p>
            @endif
            <div class="grid" aria-label="Ringkasan PUMK BRI">
                <div class="metric"><span>RKA {{ $year }}</span><strong>{{ $rupiah($rka?->nominal_rka) }}</strong></div>
                <div class="metric"><span>Realisasi penyaluran</span><strong>{{ $rupiah($realisasi) }}</strong></div>
                <div class="metric"><span>Progres</span><strong>{{ $progress === null ? 'Belum tersedia' : number_format($progress, 2, ',', '.').'%' }}</strong></div>
                <div class="metric"><span>Outstanding bulan terakhir</span><strong>{{ $rupiah($briOutstanding) }}</strong></div>
                <div class="metric"><span>Mitra bulan terakhir</span><strong>{{ $briTotalMitra === null ? 'Belum tersedia' : number_format($briTotalMitra, 0, ',', '.') }}</strong></div>
            </div>
            <section class="panel">
                <h2>Input realisasi BRI {{ $year }}</h2>
                <div class="table-wrap"><table><thead><tr><th>Diperbarui</th><th>Admin PUMK</th><th>Periode</th><th class="number">Nominal penyaluran</th></tr></thead><tbody>
                    @forelse($penyaluranBriTerbaru as $item)
                        <tr><td>{{ $item->updated_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</td><td>{{ $item->pengubah?->name ?? 'Akun tidak tersedia' }}</td><td>{{ str_pad((string) $item->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $item->tahun }}</td><td class="number">{{ $rupiah($item->nominal_penyaluran) }}</td></tr>
                    @empty<tr><td colspan="4" class="muted">Belum ada input realisasi BRI untuk tahun ini.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @endif
    </div>
</x-layouts.admin>
