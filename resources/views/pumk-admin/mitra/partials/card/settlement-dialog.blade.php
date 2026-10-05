            <dialog id="paid-loan-dialog" class="installment-dialog">
                <div class="installment-dialog-content">
                    <div class="installment-dialog-header">
                        <div><h2>Tandai Pinjaman Lunas</h2><p class="pumk-page-subtitle">Tindakan ini mengarsipkan pinjaman tanpa menghapus kartu dan histori angsuran.</p></div>
                        <button type="button" class="installment-dialog-close" data-close-paid-dialog aria-label="Tutup">&times;</button>
                    </div>
                    @if($errors->hasAny(['lunas', 'lunas_note']))
                        <div class="pumk-alert error" role="alert">{{ $errors->first('lunas') ?: $errors->first('lunas_note') }}</div>
                    @endif
                    <p>Saldo akhir kartu piutang per {{ $settlementPreview['as_of_date'] }}: pokok <strong>{{ $settlementPreview['known'] ? $rupiah($settlementPreview['saldo_pokok']) : 'Belum diketahui' }}</strong>, bunga <strong>{{ $settlementPreview['known'] ? $rupiah($settlementPreview['saldo_bunga']) : 'Belum diketahui' }}</strong>, total <strong>{{ $settlementPreview['known'] ? $rupiah($settlementPreview['total']) : 'Belum diketahui' }}</strong>.</p>
                    <p>Batas toleransi aktif: {{ $rupiah($settlementPreview['tolerance']) }}. Jenis penyelesaian menurut server: <strong>{{ match($settlementPreview['reason']) {
                        'normal' => 'Normal', 'toleransi' => 'Toleransi selisih',
                        'kelebihan_bayar' => 'Kelebihan bayar', default => 'Belum dapat ditutup',
                    } }}</strong>.</p>
                    @if($settlementPreview['eligible'])
                        <p role="status"><strong>Pinjaman ini dapat ditandai lunas.</strong>
                        @if($settlementPreview['reason'] === 'kelebihan_bayar') Saldo minus adalah kelebihan bayar; batas toleransi hanya berlaku untuk sisa utang positif. Isi catatan pelunasan di bawah.
                        @elseif($settlementPreview['needs_note']) Sisa utang ditutup sebagai toleransi selisih. Isi alasan penyelesaiannya.
                        @endif</p>
                    @endif
                    @if(! $settlementPreview['eligible'])<p class="receivable-warning">{{ $settlementPreview['message'] }}</p>@endif
                    <p>Setelah ditandai lunas, pinjaman diarsipkan dan angsuran tidak dapat ditambah atau diedit. Saldo sumber dan histori lama tetap tersimpan. Tanggal penutupan mengikuti waktu tindakan, bukan tanggal pembayaran lampau.</p>
                    <form method="POST" action="{{ route('pumk-admin.mitra.pinjaman.lunas', [$mitra, $pinjaman]) }}">
                        @csrf
                        <div class="pumk-field"><label for="lunas_note">Catatan pelunasan {{ $settlementPreview['needs_note'] ? '(wajib)' : '(opsional)' }}</label><textarea id="lunas_note" name="lunas_note" class="pumk-textarea" maxlength="1000" @if($settlementPreview['needs_note']) required @endif>{{ old('lunas_note') }}</textarea></div>
                        <div class="installment-dialog-actions"><button type="button" class="pumk-secondary-button" data-close-paid-dialog>Batal</button><button type="submit" class="danger-button" @disabled(! $settlementPreview['eligible'])>Ya, Tandai Lunas</button></div>
                    </form>
                </div>
            </dialog>
