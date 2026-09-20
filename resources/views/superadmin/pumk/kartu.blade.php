<x-layouts.admin title="Kartu Piutang PUMK - Pemantauan">
    @include('superadmin.pumk.styles')
    <div class="pumk-monitor">
        <h1>Kartu Piutang: {{ $mitra->nama_mitra }}</h1>
        <p class="intro">Tampilan baca-saja untuk Super Admin. Perubahan data dan angsuran hanya dilakukan oleh Admin PUMK.</p>
        <div class="actions">
            <a class="button secondary" href="{{ route('superadmin.pumk.mitra', ['status' => $mitra->is_active ? 'aktif' : 'lunas']) }}">Kembali ke Daftar Mitra</a>
            @if($pinjaman)
                <a class="button secondary" href="{{ route('superadmin.pumk.kartu.excel', [$mitra, $pinjaman, 'tahun' => $kartu['tahun_terpilih']]) }}">Unduh Excel</a>
                <a class="button secondary" href="{{ route('superadmin.pumk.kartu.pdf', [$mitra, $pinjaman, 'tahun' => $kartu['tahun_terpilih']]) }}">Unduh PDF</a>
            @endif
        </div>
        @if($pinjaman && $kartu)
            <form method="GET" action="{{ route('superadmin.pumk.kartu', $mitra) }}" class="panel year-filter">
                <input type="hidden" name="pinjaman" value="{{ $pinjaman->id }}">
                <label for="tahun">Tahun</label>
                <select id="tahun" name="tahun" onchange="this.form.submit()">
                    @foreach($kartu['tahun_tersedia'] as $year)
                        <option value="{{ $year }}" @selected((string) $kartu['tahun_terpilih'] === (string) $year)>{{ $year }}</option>
                    @endforeach
                    <option value="semua" @selected($kartu['tahun_terpilih'] === 'semua')>Semua Tahun</option>
                </select>
                <span class="muted">{{ $kartu['periode_label'] }}</span>
                <noscript><button class="button" type="submit">Tampilkan</button></noscript>
            </form>
        @endif
        @if($pinjamanList->count() > 1)
            <form method="GET" action="{{ route('superadmin.pumk.kartu', $mitra) }}" class="panel year-filter">
                @if($kartu)<input type="hidden" name="tahun" value="{{ $kartu['tahun_terpilih'] }}">@endif
                <label for="pinjaman">Riwayat fasilitas</label>
                <select id="pinjaman" name="pinjaman" onchange="this.form.submit()">
                    @foreach($pinjamanList as $item)
                        <option value="{{ $item->id }}" @selected($item->id === $pinjaman?->id)>{{ $item->tanggal_pencairan?->format('d/m/Y') ?? 'Tanpa tanggal' }} — {{ strtoupper($item->status ?? 'aktif') }} — Rp {{ number_format((float) $item->pinjaman_pokok, 0, ',', '.') }}</option>
                    @endforeach
                </select><noscript><button class="button" type="submit">Tampilkan</button></noscript>
            </form>
        @endif
        @if($pinjaman && $kartu)
            <div class="grid">
                <div class="metric"><span>Status</span><strong style="font-size:18px">{{ $pinjaman->status === 'lunas' ? 'Lunas / Arsip' : 'Aktif' }}</strong></div>
                <div class="metric"><span>Wilayah</span><strong style="font-size:17px">{{ $mitra->wilayah?->nama ?? $mitra->wilayah_sumber ?? '-' }}</strong></div>
                <div class="metric"><span>Pinjaman pokok</span><strong style="font-size:21px">Rp {{ number_format((float) $pinjaman->pinjaman_pokok, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Pinjaman bunga</span><strong style="font-size:21px">Rp {{ number_format((float) $pinjaman->pinjaman_bunga, 0, ',', '.') }}</strong></div>
                <div class="metric"><span>Sisa piutang</span><strong style="font-size:21px">Rp {{ number_format((float) $kartu['kekurangan'], 0, ',', '.') }}</strong></div>
            </div>
            @if($pinjaman->status === 'lunas')
                <p class="notice">Ditandai lunas pada {{ $pinjaman->lunas_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }} oleh {{ $pinjaman->pelunas?->name ?? 'akun yang tidak tersedia' }}. {{ $pinjaman->lunas_note ?: '' }}</p>
            @endif
            <section class="panel">
                <h2>Riwayat kartu piutang</h2>
                <p class="muted">{{ $kartu['periode_label'] }}</p>
                @if($kartu['jadwal_error']) <p class="notice">{{ $kartu['jadwal_error'] }}</p> @endif
                <div class="table-wrap"><table><thead><tr><th>No</th><th>Periode</th><th>Nomor Bukti</th><th class="number">Pokok Dibayar</th><th class="number">Bunga Dibayar</th><th class="number">Saldo Pokok</th><th class="number">Saldo Bunga</th></tr></thead><tbody>
                    @forelse($kartu['jadwal'] as $row)
                        <tr><td>{{ $row['no'] ?? '-' }}</td><td>{{ $row['tanggal']->format('m/Y') }}</td><td>@if(filled($row['nomor_bukti'])){{ $row['nomor_bukti'] }}@elseif(!$row['bukti_pembayaran'])-@endif @if($row['bukti_pembayaran'])<br><a class="button secondary" target="_blank" rel="noopener" href="{{ route('superadmin.pumk.angsuran.bukti.view', [$mitra, $pinjaman, $row['bukti_pembayaran']['angsuran_id']]) }}">Lihat Bukti</a> <a class="button secondary" href="{{ route('superadmin.pumk.angsuran.bukti.download', [$mitra, $pinjaman, $row['bukti_pembayaran']['angsuran_id']]) }}">Unduh</a>@endif @if($row['catatan'])<br><small class="muted">{{ $row['catatan'] }}</small>@endif</td><td class="number">{{ $row['has_payment'] ? number_format((float) $row['pokok_dibayar'], 0, ',', '.') : '-' }}</td><td class="number">{{ $row['has_payment'] ? number_format((float) $row['bunga_dibayar'], 0, ',', '.') : '-' }}</td><td class="number">{{ number_format((float) $row['saldo_pokok'], 0, ',', '.') }}</td><td class="number">{{ number_format((float) $row['saldo_bunga'], 0, ',', '.') }}</td></tr>
                    @empty<tr><td colspan="7" class="muted">Belum ada jadwal angsuran.</td></tr>@endforelse
                </tbody></table></div>
            </section>
            <section class="panel">
                <h2>Informasi tambahan mitra, pinjaman, dan jaminan</h2>
                <div class="grid">
                    @foreach(\App\Models\PumkPinjamanDokumen::CONTRACT_FIELDS as $type => $field)
                        @php($document = $pinjaman->dokumenKontrak->firstWhere('jenis_dokumen', $type))
                        <div class="metric">
                            <span>{{ str_replace('Dokumen ', '', \App\Models\PumkPinjamanDokumen::TYPES[$type]) }}</span>
                            <strong style="font-size:14px">{{ $pinjaman->getAttribute($field) ?: '-' }}</strong>
                            @if(filled($pinjaman->getAttribute($field)))
                                @if($document)
                                    <small class="muted">{{ $document->nama_file_asli }}</small>
                                    <div class="actions" style="margin-top:8px">
                                        <a class="button secondary" target="_blank" rel="noopener" href="{{ route('superadmin.pumk.dokumen.view', [$mitra, $pinjaman, $document]) }}">Lihat</a>
                                        <a class="button secondary" href="{{ route('superadmin.pumk.dokumen.download', [$mitra, $pinjaman, $document]) }}">Unduh</a>
                                    </div>
                                @else
                                    <small class="muted">Belum ada dokumen</small>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <p class="notice">Mitra ini belum memiliki fasilitas pinjaman.</p>
        @endif
    </div>
</x-layouts.admin>
