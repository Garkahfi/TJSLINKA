<x-layouts.pumk-admin title="Daftar Mitra Binaan">
    @include('pumk-admin.partials.module-styles')

    @php
        $selectedCollectibility = (string) $selectedCollectibility;
        $statusFilter = $statusFilter ?? 'aktif';
        $formatSummaryRupiah = static function (string $amount): string {
            $negative = str_starts_with($amount, '-');
            [$whole, $cents] = explode('.', ltrim($amount, '-'), 2);
            $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);

            return ($negative ? '-' : '').'Rp'.$grouped.($cents === '00' ? '' : ','.$cents);
        };
    @endphp

    <style>
        .pumk-collectibility-footer td{border-top:2px solid #cbd5e1;overflow-wrap:anywhere}
        .pumk-collectibility-footer .summary-label,.pumk-collectibility-footer .summary-amount{font-weight:700}
        .pumk-collectibility-footer .summary-amount{text-align:right;white-space:nowrap}
        .pumk-collectibility-footer .summary-note{display:block;margin-top:4px;color:#475569;font-size:11px;font-weight:400;line-height:1.4}
    </style>

    <div class="pumk-page">
        <div class="pumk-page-header">
            <div>
                <h1 class="pumk-page-title">Daftar Mitra Binaan</h1>
                <p class="pumk-page-subtitle">Cari dan pantau kartu piutang PUMK tanpa menampilkan data identitas sensitif.</p>
            </div>
            <div class="pumk-page-header-actions">
                <a href="{{ route('pumk-admin.mitra.create') }}" class="pumk-primary-button">+ Tambah Mitra Binaan</a>
            </div>
        </div>

        <nav class="pumk-card" aria-label="Status Mitra" style="display:flex;gap:8px;margin:18px 0;padding:10px;flex-wrap:wrap">
            @foreach(['aktif' => 'Mitra Aktif', 'lunas' => 'Mitra Lunas / Arsip', 'semua' => 'Semua'] as $value => $label)
                <a href="{{ route('pumk-admin.mitra.index', ['status' => $value]) }}"
                   class="{{ $statusFilter === $value ? 'pumk-primary-button' : 'pumk-secondary-button' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if(session('success'))
            <div class="pumk-alert success" role="status">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('pumk-admin.mitra.index') }}" class="pumk-card pumk-filter">
            <input type="hidden" name="status" value="{{ $statusFilter }}">
            <div class="pumk-field">
                <label for="q">Cari Nama Mitra</label>
                <input id="q" name="q" class="pumk-input" value="{{ request('q') }}" maxlength="100" placeholder="Masukkan nama mitra">
            </div>
            <div class="pumk-field">
                <label for="wilayah">Wilayah</label>
                <select id="wilayah" name="wilayah" class="pumk-select">
                    <option value="">Semua wilayah</option>
                    @foreach($wilayahList as $wilayah)
                        <option value="{{ $wilayah->id }}" @selected((string) request('wilayah') === (string) $wilayah->id)>{{ $wilayah->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pumk-field">
                <label for="sektor">Sektor Usaha</label>
                <select id="sektor" name="sektor" class="pumk-select">
                    <option value="">Semua sektor</option>
                    @foreach($sektorList as $sektor)
                        <option value="{{ $sektor->id }}" @selected((string) request('sektor') === (string) $sektor->id)>{{ $sektor->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pumk-field">
                <label for="kolektibilitas">Kolektibilitas</label>
                <select id="kolektibilitas" name="kolektibilitas" class="pumk-select">
                    <option value="">Semua status</option>
                    @foreach($collectibilityOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedCollectibility === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="pumk-primary-button" style="align-self:end">Cari</button>
            <a href="{{ route('pumk-admin.mitra.index', ['status' => $statusFilter]) }}" class="pumk-secondary-button" style="align-self:end">Reset</a>
        </form>

        <section class="pumk-card">
            <div class="pumk-table-wrap">
                <table class="pumk-table">
                    <thead>
                    <tr>
                        <th>Nama Mitra</th>
                        <th>Wilayah</th>
                        <th>Sektor Usaha</th>
                        <th>Kolektibilitas</th>
                        <th>Status Pinjaman</th>
                        <th style="text-align:right">Total Sisa</th>
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($mitraList as $mitra)
                        @php
                            $pinjaman = $mitra->pinjaman->first();
                            $status = $selectedCollectibility !== '' ? $selectedCollectibility : $pinjaman?->kolektibilitas;
                        @endphp
                        <tr>
                            <td><strong>{{ $mitra->nama_mitra }}</strong></td>
                            <td>{{ $mitra->wilayah?->nama ?? $mitra->wilayah_sumber ?? 'Belum ditentukan' }}</td>
                            <td>{{ $mitra->sektorUsaha?->nama ?? $mitra->sektor_sumber ?? 'Belum ditentukan' }}</td>
                            <td>
                                <span class="pumk-badge {{ $status ?: 'belum' }}">
                                    {{ $collectibilityOptions[$status] ?? 'Belum dihitung' }}
                                </span>
                            </td>
                            <td>{{ $pinjaman?->status === 'lunas' ? 'Lunas' : ($pinjaman?->status === 'nonaktif' ? 'Nonaktif' : 'Aktif') }}</td>
                            <td style="text-align:right;font-weight:600">
                                {{ $pinjaman && $pinjaman->total_sisa !== null ? $formatSummaryRupiah((string) $pinjaman->total_sisa) : '—' }}
                            </td>
                            <td class="actions">
                                <a href="{{ route('pumk-admin.mitra.show', [$mitra, 'pinjaman' => $pinjaman?->id]) }}" class="pumk-table-action">Lihat Kartu</a>
                                <a href="{{ route('pumk-admin.mitra.edit', [$mitra, 'pinjaman' => $pinjaman?->id]) }}" class="pumk-table-action">{{ $pinjaman?->status === 'aktif' ? 'Edit' : 'Edit Arsip' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pumk-empty">Tidak ada mitra yang sesuai dengan pencarian atau filter.</td></tr>
                    @endforelse
                    </tbody>
                    @if($collectibilitySummary !== null)
                        <tfoot class="pumk-collectibility-footer">
                        <tr>
                            <td colspan="5">
                                <span class="summary-label">{{ $collectibilitySummary['unknown_balances'] > 0 ? 'Subtotal' : 'Total' }} Sisa {{ $collectibilityOptions[$selectedCollectibility] }}:</span>
                                <span class="summary-note">Aktif dan Lunas/Arsip · seluruh halaman</span>
                                @if($collectibilitySummary['unknown_balances'] > 0)
                                    <span class="summary-note">{{ number_format($collectibilitySummary['unknown_balances'], 0, ',', '.') }} pinjaman pada kategori ini memiliki saldo belum tersedia.</span>
                                @endif
                                @if($collectibilitySummary['cache_differences'] > 0)
                                    <span class="summary-note">{{ number_format($collectibilitySummary['cache_differences'], 0, ',', '.') }} saldo cache berbeda dari kartu; rekap memakai saldo kartu tanpa mengubah data.</span>
                                @endif
                            </td>
                            <td class="summary-amount">{{ $formatSummaryRupiah($collectibilitySummary['nominal'][$selectedCollectibility]) }}</td>
                            <td></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if($mitraList->hasPages())
                <div class="pumk-pagination">{{ $mitraList->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.pumk-admin>
