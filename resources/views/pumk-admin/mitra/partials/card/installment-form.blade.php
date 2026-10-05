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
