<x-layouts.pumk-admin title="Kartu Piutang Mitra">
    @include('pumk-admin.partials.module-styles')

    @php
        $rupiah = static fn ($value) => 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
        $angka = static fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');
        $statusLabels = [
            'lancar' => 'Lancar',
            'kurang_lancar' => 'Kurang Lancar',
            'diragukan' => 'Diragukan',
            'macet' => 'Macet',
        ];
        $status = $calculation['kolektibilitas'] ?? $pinjaman?->kolektibilitas;
        $isRescheduled = filled($pinjaman?->reschedule_ke1)
            || filled($pinjaman?->reschedule_ke2)
            || filled($pinjaman?->reschedule_ke3)
            || filled($pinjaman?->reschedule_ke4);
        $saldoAwalOverlap = session('saldo_awal_overlap');
        $loanIsActive = $pinjaman?->status === \App\Models\PumkPinjaman::STATUS_AKTIF && (bool) $pinjaman?->is_active;
        $contractDocumentFields = \App\Models\PumkPinjamanDokumen::CONTRACT_FIELDS;
        $contractDocumentLabels = \App\Models\PumkPinjamanDokumen::TYPES;
        $contractDocuments = $pinjaman?->dokumenKontrak?->keyBy('jenis_dokumen') ?? collect();
    @endphp

    <style>
        .receivable-page{max-width:1280px}.receivable-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:20px}.receivable-toolbar-actions{display:flex;gap:10px;flex-wrap:wrap}.loan-selector{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 18px;padding:14px 16px}.loan-selector label{font-weight:700}.loan-selector select{min-width:280px}.paid-summary{margin:0 0 18px;border:1px solid #86efac;border-radius:9px;background:#f0fdf4;padding:13px 16px;color:#166534}.danger-button{display:inline-flex;align-items:center;min-height:40px;border:1px solid #b91c1c;border-radius:7px;background:#b91c1c;padding:8px 15px;color:#fff;font:600 13px Poppins,sans-serif;cursor:pointer}.receivable-sheet{overflow:hidden;border:2px solid #111827;border-radius:4px;background:#fff;box-shadow:0 4px 12px rgba(15,23,42,.12)}.receivable-brand{display:grid;grid-template-columns:180px minmax(0,1fr) 180px;align-items:center;min-height:82px;border-bottom:2px solid #111827;padding:10px 18px}.receivable-brand img{width:150px;max-height:56px;object-fit:contain;object-position:left center}.receivable-brand-title{text-align:center;text-transform:uppercase}.receivable-brand-title span{display:block;font-size:16px;font-weight:700;line-height:1.1}.receivable-brand-title strong{display:block;font-size:22px;line-height:1.12}.receivable-status{justify-self:end}.receivable-info{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(330px,.75fr);min-height:180px;border-bottom:2px solid #111827}.receivable-info-left{display:grid;align-content:center;padding:16px 22px}.receivable-info-row{display:grid;grid-template-columns:150px 14px minmax(0,1fr);gap:5px;padding:3px 0;font-size:14px}.receivable-info-row strong{font-weight:700}.receivable-info-right{display:grid;align-content:center;border-left:2px solid #111827;padding:14px 24px;text-align:center}.receivable-card-title{display:inline-block;justify-self:center;border:3px solid #111827;padding:5px 20px;font-size:23px;font-weight:800;letter-spacing:.02em}.receivable-region{margin-top:9px;font-size:18px;font-weight:800;text-transform:uppercase}.receivable-reschedule{min-height:22px;font-size:14px;font-style:italic;font-weight:700}.receivable-mini-finance{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:9px;text-align:left}.receivable-mini-finance div{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #64748b;padding-bottom:3px;font-size:13px}.receivable-mini-finance strong{white-space:nowrap}.receivable-table-wrap{overflow-x:auto}.receivable-table{width:100%;min-width:900px;border-collapse:collapse;color:#111827;font-size:12px;font-variant-numeric:tabular-nums}.receivable-table th,.receivable-table td{border-right:1px solid #111827;border-bottom:1px solid #111827;padding:7px 8px}.receivable-table th:last-child,.receivable-table td:last-child{border-right:0}.receivable-table thead th{background:#f1f5f9;text-align:center;font-weight:800}.receivable-table thead tr:first-child th{border-bottom:1px solid #111827;font-size:12px}.receivable-table tbody td:nth-child(n+4),.receivable-table tfoot td:last-child{text-align:right}.receivable-table tbody td:nth-child(-n+3){text-align:center}.receivable-table tbody tr.historical-month{background:#f8fafc}.receivable-table tbody tr.current-month{background:#fef3c7}.receivable-table tbody tr.source-note{background:#eff6ff}.receivable-table tbody tr:hover{background:#e0ecff}.receivable-table tbody tr.current-month:hover{background:#fde68a}.receivable-note{display:block;margin-top:3px;color:#475569;font-size:9px;line-height:1.3}.receivable-proof-actions{display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap}.receivable-edit-button{border:1px solid #2563eb;border-radius:5px;background:#fff;padding:3px 7px;color:#1d4ed8;font:600 10px Poppins,sans-serif;cursor:pointer}.receivable-edit-button:hover{background:#eff6ff}.receivable-table tfoot td{border-bottom:0;background:#fff;font-size:14px;font-weight:800}.receivable-table tfoot td:last-child{background:#fde047;font-size:15px}.receivable-warning{margin:14px;border:1px solid #f59e0b;border-radius:6px;background:#fffbeb;padding:11px 13px;color:#92400e;font-size:12px}.opening-balance-conflict{margin:16px 0;border:1px solid #d97706;border-radius:9px;background:#fffbeb;padding:16px;color:#78350f}.opening-balance-conflict h2{margin:0 0 7px;font-size:17px}.opening-balance-conflict p{margin:0;line-height:1.55}.opening-balance-conflict-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:13px}.receivable-meta{display:flex;justify-content:space-between;gap:14px;padding:8px 12px;color:#64748b;font-size:10px}.installment-panel,.receivable-details{margin-top:20px}.installment-panel{padding:18px}.installment-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.installment-heading h2{margin:0;font-size:20px}.installment-form{display:grid;grid-template-columns:1.1fr 1.45fr repeat(3,minmax(0,1fr));align-items:start;gap:16px;margin-top:18px}.installment-form-actions{display:flex;justify-content:flex-end;grid-column:1/-1;margin-top:2px}.installment-form-actions .pumk-primary-button{min-width:190px;min-height:43px}.rupiah-help{display:block;min-height:28px;margin:3px 0 0;color:#64748b;font-size:10px;line-height:1.4}.receivable-details summary{cursor:pointer;font-weight:700}.receivable-details-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.receivable-detail{border:1px solid #e2e8f0;border-radius:7px;background:#fafafa;padding:11px}.receivable-detail span{display:block;color:#64748b;font-size:10px}.receivable-detail strong{display:block;margin-top:5px;font-size:13px;overflow-wrap:anywhere}.installment-dialog{width:min(720px,calc(100% - 32px));border:0;border-radius:12px;padding:0;box-shadow:0 24px 60px rgba(15,23,42,.3)}.installment-dialog::backdrop{background:rgba(15,23,42,.58)}.installment-dialog-content{padding:22px}.installment-dialog-header{display:flex;align-items:center;justify-content:space-between;gap:16px}.installment-dialog-header h2{margin:0;font-size:21px}.installment-dialog-close{border:0;background:transparent;font-size:27px;line-height:1;cursor:pointer}.installment-dialog-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-top:18px}.installment-dialog-actions{display:flex;justify-content:flex-end;gap:10px;grid-column:1/-1;margin-top:8px}@media(max-width:900px){.receivable-brand{grid-template-columns:130px 1fr}.receivable-brand img{width:115px}.receivable-status{display:none}.receivable-info{grid-template-columns:1fr}.receivable-info-right{border-top:2px solid #111827;border-left:0}.installment-form{grid-template-columns:repeat(2,minmax(0,1fr))}.installment-form-actions{grid-column:1/-1}.receivable-details-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.receivable-toolbar{flex-direction:column}.receivable-toolbar-actions{width:100%}.receivable-toolbar-actions a{flex:1}.receivable-brand{grid-template-columns:95px 1fr;min-height:68px;padding:8px 10px}.receivable-brand img{width:88px}.receivable-brand-title span{font-size:10px}.receivable-brand-title strong{font-size:14px}.receivable-info-row{grid-template-columns:115px 10px 1fr;font-size:11px}.receivable-info-left,.receivable-info-right{padding:13px}.receivable-card-title{font-size:18px}.installment-form,.receivable-details-grid,.installment-dialog-form{grid-template-columns:1fr}.installment-form-actions{grid-column:1}.installment-form-actions .pumk-primary-button{width:100%}.installment-dialog-actions{grid-column:1}}@media print{.pumk-header,.pumk-sidebar,.pumk-mobile-toggle,.receivable-toolbar,.loan-selector,.opening-balance-conflict,.installment-panel,.receivable-details,.receivable-edit-button,.installment-dialog{display:none!important}.pumk-main{margin:0!important;padding:0!important}.receivable-sheet{box-shadow:none}.receivable-table th,.receivable-table td{padding:4px 5px}.receivable-table{font-size:9px}}
    </style>
    <style>
        .year-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 18px;padding:13px 16px}.year-toolbar label{font-weight:700}.year-toolbar select{min-width:170px}.opening-balance-proof{flex-basis:100%}.proof-input-group{display:grid;gap:7px}.proof-input-group input[type=file]{font-size:10px}.payment-proof-links{display:flex;justify-content:center;gap:5px;flex-wrap:wrap;margin-top:4px}.edit-proof-existing{border:1px solid #dbeafe;border-radius:6px;background:#eff6ff;padding:8px;font-size:11px}.contract-document-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.contract-document-actions form{margin:0}.small-action{display:inline-flex;align-items:center;border:1px solid #94a3b8;border-radius:5px;background:#fff;padding:4px 8px;color:#0f172a;font:600 10px Poppins,sans-serif;text-decoration:none}.small-action.danger{border-color:#fca5a5;color:#b91c1c}
    </style>

    <div class="pumk-page receivable-page">
        <div class="receivable-toolbar">
            <div>
                <h1 class="pumk-page-title">Kartu Piutang Mitra</h1>
                <p class="pumk-page-subtitle">Jadwal angsuran lengkap {{ $mitra->nama_mitra }}</p>
            </div>
            <div class="receivable-toolbar-actions">
                <a href="{{ route('pumk-admin.mitra.index') }}" class="pumk-secondary-button">Kembali</a>
                @if($pinjaman)
                    <a href="{{ route('pumk-admin.mitra.kartu.excel', [$mitra, $pinjaman, 'tahun' => $kartu['tahun_terpilih']]) }}" class="pumk-secondary-button">Unduh Excel</a>
                    <a href="{{ route('pumk-admin.mitra.kartu.pdf', [$mitra, $pinjaman, 'tahun' => $kartu['tahun_terpilih']]) }}" class="pumk-secondary-button">Unduh PDF</a>
                @endif
                <a href="{{ route('pumk-admin.mitra.edit', $mitra) }}" class="pumk-primary-button">{{ $mitra->is_active ? 'Edit Data' : 'Aktifkan Kembali' }}</a>
                @if($loanIsActive)
                    <button type="button" class="danger-button" data-open-paid-dialog>Tandai Lunas</button>
                @endif
            </div>
        </div>

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

        @if(session('success'))<div class="pumk-alert success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>Angsuran belum disimpan:</strong>
                <ul style="margin:8px 0 0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
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
        @if($pinjaman?->status === \App\Models\PumkPinjaman::STATUS_LUNAS)
            <div class="paid-summary" role="status">
                <strong>Pinjaman lunas.</strong>
                Ditandai pada {{ $pinjaman->lunas_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}.
                @if(filled($pinjaman->lunas_note)) Catatan: {{ $pinjaman->lunas_note }} @endif
            </div>
        @endif
        @if($saldoAwalOverlap)
            <section class="opening-balance-conflict" role="alert" aria-labelledby="opening-balance-conflict-title">
                <h2 id="opening-balance-conflict-title">Konfirmasi histori sampai {{ $saldoAwalOverlap['cutoff'] }}</h2>
                <p>
                    Periode <strong>{{ $saldoAwalOverlap['periode'] }}</strong> termasuk dalam Saldo Awal per
                    <strong>{{ $saldoAwalOverlap['cutoff'] }}</strong>. Data tersebut kemungkinan sudah termasuk
                    dalam saldo awal. Melanjutkan tanpa penyesuaian dapat menyebabkan pembayaran dihitung dua kali.
                    Jika dilanjutkan, Saldo Awal akan dihapus dan saldo pinjaman dihitung dari angsuran rinci.
                </p>
                <form method="POST" action="{{ $saldoAwalOverlap['action'] }}" class="opening-balance-conflict-actions" enctype="multipart/form-data">
                    @csrf
                    @if($saldoAwalOverlap['method'] !== 'POST')
                        @method($saldoAwalOverlap['method'])
                    @endif
                    <input type="hidden" name="periode" value="{{ old('periode') }}">
                    <input type="hidden" name="nomor_bukti" value="{{ old('nomor_bukti') }}">
                    <input type="hidden" name="pokok" value="{{ old('pokok') }}">
                    <input type="hidden" name="bunga" value="{{ old('bunga') }}">
                    <input type="hidden" name="denda" value="{{ old('denda') }}">
                    <input type="hidden" name="hapus_saldo_awal" value="1">
                    @if($saldoAwalOverlap['proof_was_uploaded'] ?? false)
                        <div class="pumk-field opening-balance-proof">
                            <label for="overlap-bukti-pembayaran">Pilih ulang bukti pembayaran</label>
                            <input id="overlap-bukti-pembayaran" type="file" name="bukti_pembayaran" class="pumk-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <small>File yang dipilih sebelumnya tidak dapat dibawa melewati konfirmasi ini.</small>
                        </div>
                    @endif
                    <button type="submit" class="pumk-primary-button">Ya, Hapus Saldo Awal &amp; Simpan</button>
                    <a href="{{ route('pumk-admin.mitra.show', $mitra) }}" class="pumk-secondary-button">Tidak, Batalkan</a>
                </form>
            </section>
        @endif

        @if($pinjaman && $kartu)
            <section class="receivable-sheet" aria-labelledby="receivable-card-title">
                <header class="receivable-brand">
                    <img src="{{ asset('images/logo/inka.png') }}" alt="INKA">
                    <div class="receivable-brand-title">
                        <span>Program</span>
                        <strong>Kemitraan dan Bina Lingkungan</strong>
                    </div>
                    <span class="pumk-badge receivable-status {{ $status ?: 'belum' }}">{{ $statusLabels[$status] ?? 'Belum dihitung' }}</span>
                </header>

                <div class="receivable-info">
                    <div class="receivable-info-left">
                        <div class="receivable-info-row"><span>Nama Perusahaan</span><span>:</span><strong>{{ $mitra->nama_mitra }}</strong></div>
                        <div class="receivable-info-row"><span>Pemilik</span><span>:</span><strong>{{ $mitra->nama_pemilik ?: '-' }}</strong></div>
                        <div class="receivable-info-row"><span>Angsuran Pertama</span><span>:</span><strong>{{ $pinjaman->mulai_angsuran?->translatedFormat('F Y') ?? '-' }}</strong></div>
                        <div class="receivable-info-row"><span>Jatuh Tempo</span><span>:</span><strong>{{ $pinjaman->selesai_angsuran?->translatedFormat('F Y') ?? '-' }}{{ $kartu['tenor'] ? ' ('.$kartu['tenor'].'x)' : '' }}</strong></div>
                        <div class="receivable-info-row"><span>Jumlah Pinjaman</span><span>:</span><strong>{{ $rupiah($pinjaman->pinjaman_pokok) }}</strong></div>
                    </div>
                    <div class="receivable-info-right">
                        <h2 id="receivable-card-title" class="receivable-card-title">KARTU PIUTANG</h2>
                        <div class="receivable-region">{{ $mitra->wilayah?->nama ?? $mitra->wilayah_sumber ?? 'Wilayah belum diisi' }}</div>
                        <div class="receivable-reschedule">{{ $isRescheduled ? 'Rescheduling' : '' }}</div>
                        <div class="receivable-mini-finance">
                            <div><span>Bunga</span><strong>{{ $rupiah($pinjaman->pinjaman_bunga) }}</strong></div>
                            <div><span>Angs/bln</span><strong>{{ $rupiah($pinjaman->nilai_angsuran_bulanan) }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="receivable-meta"><strong>{{ $kartu['periode_label'] }}</strong></div>

                @if($kartu['jadwal_error'])<div class="receivable-warning">{{ $kartu['jadwal_error'] }}</div>@endif

                <div class="receivable-table-wrap">
                    <table class="receivable-table">
                        <thead>
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">Tanggal</th>
                                <th rowspan="2">Nomor Bukti<br>Pembayaran</th>
                                <th colspan="3">Pembayaran Angsuran (Rp)</th>
                                <th colspan="2">Saldo Pinjaman</th>
                            </tr>
                            <tr><th>Pokok</th><th>Bunga</th><th>Total</th><th>Pokok</th><th>Bunga</th></tr>
                        </thead>
                        <tbody>
                        @forelse($kartu['jadwal'] as $row)
                            <tr data-schedule-row @class(['historical-month' => $row['is_historis'], 'current-month' => $row['is_bulan_berjalan'], 'source-note' => filled($row['catatan']) && ! $row['is_bulan_berjalan']])>
                                <td>{{ $row['no'] ?? '-' }}</td>
                                <td>{{ $row['tanggal']->format('d-M-y') }}</td>
                                <td>
                                    <div class="receivable-proof-actions">
                                        @if(filled($row['nomor_bukti']))
                                            <span>{{ $row['nomor_bukti'] }}</span>
                                        @elseif(!$row['bukti_pembayaran'])
                                            <span>-</span>
                                        @endif
                                        @if($row['bukti_pembayaran'])
                                            <div class="payment-proof-links">
                                                <a class="small-action" target="_blank" rel="noopener" href="{{ route('pumk-admin.mitra.angsuran.bukti.view', [$mitra, $pinjaman, $row['bukti_pembayaran']['angsuran_id']]) }}">Lihat Bukti</a>
                                                <a class="small-action" href="{{ route('pumk-admin.mitra.angsuran.bukti.download', [$mitra, $pinjaman, $row['bukti_pembayaran']['angsuran_id']]) }}">Unduh</a>
                                            </div>
                                        @endif
                                        @if($loanIsActive && $row['editable_angsuran'])
                                            <button type="button" class="receivable-edit-button"
                                                data-edit-installment
                                                data-update-url="{{ route('pumk-admin.mitra.angsuran.update', [$mitra, $pinjaman, $row['editable_angsuran']['id']]) }}"
                                                data-periode="{{ $row['editable_angsuran']['periode'] }}"
                                                data-nomor-bukti="{{ $row['editable_angsuran']['nomor_bukti'] }}"
                                                data-has-bukti="{{ $row['editable_angsuran']['has_bukti'] ? '1' : '0' }}"
                                                data-bukti-nama="{{ $row['editable_angsuran']['bukti_nama_asli'] }}"
                                                data-bukti-view-url="{{ $row['editable_angsuran']['has_bukti'] ? route('pumk-admin.mitra.angsuran.bukti.view', [$mitra, $pinjaman, $row['editable_angsuran']['id']]) : '' }}"
                                                data-bukti-delete-url="{{ $row['editable_angsuran']['has_bukti'] ? route('pumk-admin.mitra.angsuran.bukti.destroy', [$mitra, $pinjaman, $row['editable_angsuran']['id']]) : '' }}"
                                                data-pokok="{{ (int) round((float) $row['editable_angsuran']['pokok']) }}"
                                                data-bunga="{{ (int) round((float) $row['editable_angsuran']['bunga']) }}"
                                                data-denda="{{ (int) round((float) $row['editable_angsuran']['denda']) }}">Edit</button>
                                        @endif
                                    </div>
                                    @if(filled($row['catatan']))<small class="receivable-note">{{ $row['catatan'] }}</small>@endif
                                </td>
                                <td>{{ $row['has_payment'] ? $angka($row['pokok_dibayar']) : '-' }}</td>
                                <td>{{ $row['has_payment'] ? $angka($row['bunga_dibayar']) : '-' }}</td>
                                <td>{{ $row['has_payment'] ? $angka($row['total_dibayar']) : '-' }}</td>
                                <td>{{ $angka($row['saldo_pokok']) }}</td>
                                <td>{{ $angka($row['saldo_bunga']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="pumk-empty">Belum ada jadwal yang dapat ditampilkan.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot><tr><td colspan="7" style="text-align:right">Kekurangan</td><td>{{ $angka($kartu['kekurangan']) }}</td></tr></tfoot>
                    </table>
                </div>
                <div class="receivable-meta">
                    <span>Form Kartu Piutang PUMK</span>
                    @if((float) $kartu['denda'] > 0)<span>Denda tercatat: {{ $rupiah($kartu['denda']) }}</span>@endif
                </div>
            </section>

            @if($loanIsActive)
            <section id="installment-panel" class="pumk-card installment-panel">
                <div class="installment-heading">
                    <div><h2>Tambah Angsuran</h2><p class="pumk-page-subtitle">Pembayaran langsung masuk ke bulan yang sesuai pada kartu.</p></div>
                </div>
                <form method="POST" action="{{ route('pumk-admin.mitra.angsuran.store', [$mitra, $pinjaman]) }}" class="installment-form" enctype="multipart/form-data" data-saldo-awal-cutoff="{{ $pinjaman->saldoAwal?->cutoff_date?->format('Y-m') }}">
                    @csrf
                    <input type="hidden" name="hapus_saldo_awal" value="0">
                    <div class="pumk-field"><label for="periode">Periode</label><input id="periode" type="month" name="periode" class="pumk-input" value="{{ old('periode') }}" required><small class="rupiah-help">Tahun lama seperti 2010 atau sebelumnya tetap dapat dipilih.</small></div>
                    <div class="pumk-field"><label for="nomor_bukti">Nomor &amp; Bukti Pembayaran</label><div class="proof-input-group"><input id="nomor_bukti" name="nomor_bukti" class="pumk-input" value="{{ old('nomor_bukti') }}" maxlength="255" placeholder="Contoh: BKM/2026/001"><input id="bukti_pembayaran" type="file" name="bukti_pembayaran" class="pumk-input" accept=".pdf,.jpg,.jpeg,.png"><small class="rupiah-help">Opsional. PDF/JPG/JPEG/PNG, maksimal {{ number_format(config('pumk.payment_proof_max_kb') / 1024, 0, ',', '.') }} MB.</small></div></div>
                    <div class="pumk-field"><label for="pokok">Pokok (Rp)</label><input id="pokok" type="text" inputmode="numeric" name="pokok" class="pumk-input" data-rupiah-input value="{{ old('pokok') }}" autocomplete="off" required><small class="rupiah-help">Contoh: ketik 333400, tampil 333.400</small></div>
                    <div class="pumk-field"><label for="bunga">Bunga (Rp)</label><input id="bunga" type="text" inputmode="numeric" name="bunga" class="pumk-input" data-rupiah-input value="{{ old('bunga', '-') }}" autocomplete="off" required><small class="rupiah-help">Gunakan - jika tidak ada bunga</small></div>
                    <div class="pumk-field"><label for="denda">Denda (Rp)</label><input id="denda" type="text" inputmode="numeric" name="denda" class="pumk-input" data-rupiah-input value="{{ old('denda', '-') }}" autocomplete="off"><small class="rupiah-help">Gunakan - jika tidak ada denda</small></div>
                    <div class="installment-form-actions">
                        <button type="submit" class="pumk-primary-button">Simpan Angsuran</button>
                    </div>
                </form>
            </section>
            @endif

            <details class="pumk-card installment-panel receivable-details">
                <summary>Informasi tambahan mitra, pinjaman, dan jaminan</summary>
                <div class="receivable-details-grid">
                    @foreach($contractDocumentFields as $type => $field)
                        @php($document = $contractDocuments->get($type))
                        <div class="receivable-detail">
                            <span>{{ str_replace('Dokumen ', '', $contractDocumentLabels[$type]) }}</span>
                            <strong>{{ $pinjaman->getAttribute($field) ?: '-' }}</strong>
                            @if(filled($pinjaman->getAttribute($field)))
                                @if($document)
                                    <small class="receivable-note">{{ $document->nama_file_asli }}</small>
                                    <div class="contract-document-actions">
                                        <a class="small-action" target="_blank" rel="noopener" href="{{ route('pumk-admin.mitra.dokumen.view', [$mitra, $pinjaman, $document]) }}">Lihat</a>
                                        <a class="small-action" href="{{ route('pumk-admin.mitra.dokumen.download', [$mitra, $pinjaman, $document]) }}">Unduh</a>
                                        @if($loanIsActive)
                                            <form method="POST" action="{{ route('pumk-admin.mitra.dokumen.destroy', [$mitra, $pinjaman, $document]) }}" onsubmit="return confirm('Hapus dokumen kontrak ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="small-action danger">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <small class="receivable-note">Belum ada dokumen</small>
                                @endif
                            @endif
                        </div>
                    @endforeach
                    <div class="receivable-detail"><span>Jenis / Sektor Usaha</span><strong>{{ $mitra->jenis_usaha ?: '-' }}{{ $mitra->sektorUsaha?->nama ? ' / '.$mitra->sektorUsaha->nama : '' }}</strong></div>
                    <div class="receivable-detail"><span>Alamat</span><strong>{{ $mitra->alamat ?: '-' }}</strong></div>
                    <div class="receivable-detail"><span>Jenis Jaminan</span><strong>{{ $pinjaman->jenis_jaminan ?: '-' }}</strong></div>
                    <div class="receivable-detail"><span>Nomor Polisi / BPKB</span><strong>{{ collect([$pinjaman->jaminan_no_pol, $pinjaman->jaminan_no_bpkb])->filter()->implode(' / ') ?: '-' }}</strong></div>
                    <div class="receivable-detail"><span>Sertifikat / Atas Nama</span><strong>{{ collect([$pinjaman->jaminan_no_sertifikat, $pinjaman->jaminan_atas_nama])->filter()->implode(' / ') ?: '-' }}</strong></div>
                </div>
            </details>

            @if($loanIsActive)
            <dialog id="edit-installment-dialog" class="installment-dialog">
                <div class="installment-dialog-content">
                    <div class="installment-dialog-header">
                        <div><h2>Edit Angsuran</h2><p class="pumk-page-subtitle">Perubahan akan langsung menghitung ulang saldo kartu.</p></div>
                        <button type="button" class="installment-dialog-close" data-close-installment-dialog aria-label="Tutup">&times;</button>
                    </div>
                    <form id="edit-installment-form" method="POST" class="installment-dialog-form" enctype="multipart/form-data" data-saldo-awal-cutoff="{{ $pinjaman->saldoAwal?->cutoff_date?->format('Y-m') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="hapus_saldo_awal" value="0">
                        <div class="pumk-field"><label for="edit-periode">Periode</label><input id="edit-periode" type="month" name="periode" class="pumk-input" required><small class="rupiah-help">Periode angsuran lama tetap dapat dipilih.</small></div>
                        <div class="pumk-field"><label for="edit-nomor-bukti">Nomor &amp; Bukti Pembayaran</label><div class="proof-input-group"><input id="edit-nomor-bukti" name="nomor_bukti" class="pumk-input" maxlength="255"><input id="edit-bukti-pembayaran" type="file" name="bukti_pembayaran" class="pumk-input" accept=".pdf,.jpg,.jpeg,.png"><div class="edit-proof-existing" data-edit-proof-existing hidden><span data-edit-proof-name></span><div class="contract-document-actions"><a class="small-action" data-edit-proof-view target="_blank" rel="noopener">Lihat</a><button class="small-action" type="button" data-choose-payment-proof>Ganti Bukti</button><button class="small-action danger" type="button" data-delete-payment-proof>Hapus Bukti</button></div></div><small class="rupiah-help">File baru akan mengganti bukti lama. Upload bersifat opsional.</small></div></div>
                        <div class="pumk-field"><label for="edit-pokok">Pokok (Rp)</label><input id="edit-pokok" type="text" inputmode="numeric" name="pokok" class="pumk-input" data-rupiah-input autocomplete="off" required></div>
                        <div class="pumk-field"><label for="edit-bunga">Bunga (Rp)</label><input id="edit-bunga" type="text" inputmode="numeric" name="bunga" class="pumk-input" data-rupiah-input autocomplete="off" required><small class="rupiah-help">Gunakan - jika tidak ada bunga</small></div>
                        <div class="pumk-field"><label for="edit-denda">Denda (Rp)</label><input id="edit-denda" type="text" inputmode="numeric" name="denda" class="pumk-input" data-rupiah-input autocomplete="off"><small class="rupiah-help">Gunakan - jika tidak ada denda</small></div>
                        <div class="installment-dialog-actions"><button type="button" class="pumk-secondary-button" data-close-installment-dialog>Batal</button><button type="submit" class="pumk-primary-button">Simpan Perubahan</button></div>
                    </form>
                </div>
            </dialog>
            <form id="delete-payment-proof-form" method="POST" hidden>
                @csrf @method('DELETE')
            </form>
            <dialog id="paid-loan-dialog" class="installment-dialog">
                <div class="installment-dialog-content">
                    <div class="installment-dialog-header">
                        <div><h2>Tandai Pinjaman Lunas</h2><p class="pumk-page-subtitle">Tindakan ini mengarsipkan pinjaman tanpa menghapus kartu dan histori angsuran.</p></div>
                        <button type="button" class="installment-dialog-close" data-close-paid-dialog aria-label="Tutup">&times;</button>
                    </div>
                    <p>Pastikan saldo pokok, saldo bunga, dan kekurangan pada kartu sudah nol. Setelah dilanjutkan, angsuran tidak dapat ditambah atau diedit pada fasilitas ini.</p>
                    <form method="POST" action="{{ route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $pinjaman]) }}">
                        @csrf
                        <div class="pumk-field"><label for="lunas_note">Catatan pelunasan (opsional)</label><textarea id="lunas_note" name="lunas_note" class="pumk-textarea" maxlength="1000">{{ old('lunas_note') }}</textarea></div>
                        <div class="installment-dialog-actions"><button type="button" class="pumk-secondary-button" data-close-paid-dialog>Batal</button><button type="submit" class="danger-button">Ya, Tandai Lunas</button></div>
                    </form>
                </div>
            </dialog>
            @endif
        @else
            <section class="pumk-card pumk-empty">Data pinjaman belum tersedia. Gunakan tombol Edit Data untuk melengkapinya.</section>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const formatter = new Intl.NumberFormat('id-ID');
            const formatRupiah = (value) => {
                const trimmed = String(value ?? '').trim();
                if (trimmed === '-') return '-';
                const digits = trimmed.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
                return digits === '' ? '' : formatter.format(Number(digits));
            };
            const applyFormat = (input) => { input.value = formatRupiah(input.value); };

            document.querySelectorAll('[data-rupiah-input]').forEach((input) => {
                applyFormat(input);
                input.addEventListener('input', () => applyFormat(input));
            });

            const confirmHistoricalOverlap = (form) => {
                const cutoff = form.dataset.saldoAwalCutoff;
                const periode = form.querySelector('[name="periode"]')?.value;
                const confirmation = form.querySelector('[name="hapus_saldo_awal"]');
                if (confirmation) confirmation.value = '0';
                if (!cutoff || !periode || periode > cutoff) return true;
                if (!window.confirm('Periode ini termasuk dalam Saldo Awal. Jika dilanjutkan, Saldo Awal akan dihapus agar pembayaran tidak dihitung dua kali. Lanjutkan?')) return false;
                if (confirmation) confirmation.value = '1';
                return true;
            };
            document.querySelector('.installment-form')?.addEventListener('submit', (event) => {
                if (!confirmHistoricalOverlap(event.currentTarget)) event.preventDefault();
            });

            const dialog = document.getElementById('edit-installment-dialog');
            const form = document.getElementById('edit-installment-form');
            if (dialog && form) {
                const proofFile = form.querySelector('[name="bukti_pembayaran"]');
                const proofExisting = form.querySelector('[data-edit-proof-existing]');
                const proofName = form.querySelector('[data-edit-proof-name]');
                const proofView = form.querySelector('[data-edit-proof-view]');
                const proofChoose = form.querySelector('[data-choose-payment-proof]');
                const proofDelete = form.querySelector('[data-delete-payment-proof]');
                document.querySelectorAll('[data-edit-installment]').forEach((button) => {
                    button.addEventListener('click', () => {
                        form.action = button.dataset.updateUrl;
                        form.querySelector('[name="periode"]').value = button.dataset.periode || '';
                        form.querySelector('[name="nomor_bukti"]').value = button.dataset.nomorBukti || '';
                        form.querySelector('[name="pokok"]').value = formatRupiah(button.dataset.pokok);
                        form.querySelector('[name="bunga"]').value = Number(button.dataset.bunga) === 0 ? '-' : formatRupiah(button.dataset.bunga);
                        form.querySelector('[name="denda"]').value = Number(button.dataset.denda) === 0 ? '-' : formatRupiah(button.dataset.denda);
                        if (proofFile) {
                            proofFile.value = '';
                            proofFile.dataset.hasExisting = button.dataset.hasBukti || '0';
                        }
                        if (proofExisting) proofExisting.hidden = button.dataset.hasBukti !== '1';
                        if (proofName) proofName.textContent = button.dataset.buktiNama || '';
                        if (proofView) proofView.href = button.dataset.buktiViewUrl || '#';
                        if (proofDelete) proofDelete.dataset.deleteUrl = button.dataset.buktiDeleteUrl || '';
                        dialog.showModal();
                    });
                });
                form.addEventListener('submit', (event) => {
                    if (proofFile?.dataset.hasExisting === '1' && proofFile.files.length > 0
                        && !window.confirm('Bukti pembayaran lama akan diganti dengan file baru. Lanjutkan?')) {
                        event.preventDefault();
                        return;
                    }
                    if (!confirmHistoricalOverlap(form)) event.preventDefault();
                });
                proofChoose?.addEventListener('click', () => proofFile?.click());
                proofDelete?.addEventListener('click', () => {
                    if (!proofDelete.dataset.deleteUrl) return;
                    if (!window.confirm('Bukti pembayaran akan dihapus dari transaksi ini. Data angsuran tetap tersimpan.\n\nLanjutkan?')) return;
                    const deleteForm = document.getElementById('delete-payment-proof-form');
                    deleteForm.action = proofDelete.dataset.deleteUrl;
                    deleteForm.submit();
                });
                document.querySelectorAll('[data-close-installment-dialog]').forEach((button) => button.addEventListener('click', () => dialog.close()));
                dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
            }

            const paidDialog = document.getElementById('paid-loan-dialog');
            if (paidDialog) {
                document.querySelector('[data-open-paid-dialog]')?.addEventListener('click', () => paidDialog.showModal());
                document.querySelectorAll('[data-close-paid-dialog]').forEach((button) => button.addEventListener('click', () => paidDialog.close()));
                paidDialog.addEventListener('click', (event) => { if (event.target === paidDialog) paidDialog.close(); });
            }
        });
    </script>
</x-layouts.pumk-admin>
