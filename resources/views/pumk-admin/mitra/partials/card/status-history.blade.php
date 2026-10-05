        @if($pinjaman?->status === \App\Models\PumkPinjaman::STATUS_LUNAS)
            <div class="paid-summary" role="status">
                <strong>Pinjaman lunas.</strong>
                Ditandai pada {{ $pinjaman->lunas_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}.
                @if($pinjaman->lunas_reason)
                    Jenis penyelesaian: {{ match($pinjaman->lunas_reason) {
                        'normal' => 'Normal', 'toleransi' => 'Toleransi selisih',
                        'kelebihan_bayar' => 'Kelebihan bayar', default => 'Perlu ditinjau',
                    } }}.
                    Saldo saat ditutup: pokok {{ $rupiah($pinjaman->lunas_saldo_pokok) }},
                    bunga {{ $rupiah($pinjaman->lunas_saldo_bunga) }},
                    total {{ $rupiah($pinjaman->lunas_total_saldo) }}.
                    Batas toleransi saat tindakan: {{ $rupiah($pinjaman->lunas_tolerance_applied) }}.
                @else
                    Pelunasan lama; rincian selisih belum tercatat.
                @endif
                @if($pinjaman->pelunas) Petugas: {{ $pinjaman->pelunas->name }}. @endif
                @if(filled($pinjaman->lunas_note)) Catatan: {{ $pinjaman->lunas_note }} @endif
            </div>
        @endif
        @if($pinjaman?->status === \App\Models\PumkPinjaman::STATUS_LUNAS)
            <details class="pumk-card" style="padding:16px;margin-bottom:18px">
                <summary>Buka Kembali Pinjaman Lama</summary>
                <p>Gunakan hanya untuk koreksi pinjaman ini. Saldo, nomor pinjaman, dan angsuran lama tetap dipakai. Unggah SPJ cukup melalui Edit Arsip dan Dokumen.</p>
                <form method="POST" action="{{ route('pumk-admin.mitra.pinjaman.reopen', [$mitra, $pinjaman]) }}">
                    @csrf
                    <label for="reopen_note">Alasan membuka kembali *</label>
                    <textarea id="reopen_note" name="reopen_note" class="pumk-textarea" required minlength="5" maxlength="1000">{{ old('reopen_note') }}</textarea>
                    <button type="submit" class="pumk-secondary-button">Buka Kembali Pinjaman Ini</button>
                </form>
            </details>
        @endif
        @if($pinjaman && $pinjaman->closures->contains(fn ($closure) => $closure->reopened_at !== null))
            <details class="pumk-card" style="padding:16px;margin-bottom:18px"><summary>Riwayat penutupan dan pembukaan kembali</summary>
                @foreach($pinjaman->closures->whereNotNull('reopened_at') as $closure)
                    <p>Ditutup {{ $closure->closed_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}, dibuka kembali {{ $closure->reopened_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}.
                    Saldo saat ditutup: {{ ($closure->settlement_snapshot['lunas_total_saldo'] ?? null) !== null ? $rupiah($closure->settlement_snapshot['lunas_total_saldo']) : 'Belum tercatat' }}. Alasan: {{ $closure->reopen_note }}</p>
                @endforeach
            </details>
        @endif
