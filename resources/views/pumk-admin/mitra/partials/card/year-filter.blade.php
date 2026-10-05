        @if($pinjaman && $kartu)
            <form method="GET" action="{{ route('pumk-admin.mitra.show', $mitra) }}" class="pumk-card year-toolbar">
                <input type="hidden" name="pinjaman" value="{{ $pinjaman->id }}">
                <label for="tahun">Tahun</label>
                <select id="tahun" name="tahun" class="pumk-select" onchange="this.form.submit()">
                    @foreach($kartu['tahun_tersedia'] as $year)
                        <option value="{{ $year }}" @selected((string) $kartu['tahun_terpilih'] === (string) $year)>{{ $year }}</option>
                    @endforeach
                    <option value="semua" @selected($kartu['tahun_terpilih'] === 'semua')>Semua Tahun</option>
                </select>
                <noscript><button type="submit" class="pumk-secondary-button">Tampilkan</button></noscript>
            </form>
        @endif
