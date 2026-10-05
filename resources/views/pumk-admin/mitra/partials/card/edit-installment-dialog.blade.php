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
