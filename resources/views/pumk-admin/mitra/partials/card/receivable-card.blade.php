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
                @if($calculation['menggunakan_baseline_sumber'] ?? false)
                    <div class="receivable-warning">{{ $calculation['catatan_perhitungan'] ?? 'Posisi dihitung dari jadwal dan pembayaran yang tercatat.' }}</div>
                @endif

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
