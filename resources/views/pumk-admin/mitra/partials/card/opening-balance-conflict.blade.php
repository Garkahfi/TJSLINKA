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
