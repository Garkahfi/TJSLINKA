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
                @if($errors->hasAny(['lunas', 'lunas_note']))
                    paidDialog.showModal();
                @endif
            }
        });
    </script>
