        @if($pinjamanList->count() > 1)
            <form method="GET" action="{{ route('pumk-admin.mitra.show', $mitra) }}" class="pumk-card loan-selector">
                @if($kartu)<input type="hidden" name="tahun" value="{{ $kartu['tahun_terpilih'] }}">@endif
                <label for="pinjaman">Riwayat fasilitas pinjaman</label>
                <select id="pinjaman" name="pinjaman" class="pumk-select" onchange="this.form.submit()">
                    @foreach($pinjamanList as $item)
                        <option value="{{ $item->id }}" @selected($item->id === $pinjaman?->id)>
                            {{ $item->tanggal_pencairan?->format('d/m/Y') ?? 'Tanpa tanggal' }} — {{ strtoupper($item->status ?? 'aktif') }} — {{ $rupiah($item->pinjaman_pokok) }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="pumk-secondary-button" type="submit">Tampilkan</button></noscript>
            </form>
        @endif
