# Pelunasan, arsip, dan pembukaan kembali PUMK

Perubahan ini memisahkan edit administrasi dan membuka kembali pinjaman lama. Aksi Buat Pinjaman Baru dihapus sesuai revisi pengguna tanggal 30 September 2026, termasuk pemrosesan servernya. Implementasi lama membuat fasilitas baru ketika mitra arsip disimpan melalui Edit/Aktifkan Kembali. Pinjaman kosong tersebut dapat terlihat bernilai nol pada kartu meskipun dasar saldonya belum diketahui oleh server.

## Aturan pelunasan

- Dasar keputusan adalah **total saldo akhir Kartu Piutang** (`PiutangCalculator::total_sisa`), sama untuk pratinjau dan POST pelunasan. Filter tahun tampilan tidak mengganti saldo akhir seluruh kartu.
- Total negatif berapa pun: kelebihan bayar, boleh ditandai lunas dengan catatan wajib. Pokok dan bunga boleh berbeda tanda. Tidak ada checkbox tambahan.
- Total nol: pelunasan normal, termasuk pokok positif yang diimbangi bunga negatif atau sebaliknya.
- Total positif sampai dengan Rp100.000 (inklusif): boleh ditandai lunas dengan catatan wajib. Di atas batas ditolak. Nilai formulir tidak dapat mengganti hasil perhitungan server.
- Contoh Arsya Lele: pokok Rp2.185 + bunga -Rp23.521 = total -Rp21.336, memenuhi syarat kelebihan bayar. Meubel Wahyu Lestari dengan total -Rp193.232 juga memenuhi syarat.
- Saldo sumber, komponen bertanda, dan histori pembayaran tetap disimpan; tidak dibuat pembayaran atau pengembalian dana fiktif dan saldo kartu tidak diubah menjadi nol.
- Data yang benar-benar tidak memiliki dasar pokok/bunga tetap perlu dilengkapi; null bukan saldo nol.

Aturan lama yang menolak komponen berbeda tanda dan perbedaan antara kartu terkini dengan resolver posisi historis telah dihapus dari keputusan pelunasan. Resolver historis tetap digunakan untuk laporan posisi pada tanggal tertentu. Audit pelunasan mengikuti aturan total yang sama.

## Penerapan pada instalasi pengguna

Setelah perubahan ditinjau dan dipasang, jalankan migration biasa `php artisan migrate` pada koneksi yang benar. Migration menyalin metadata penutupan lama ke riwayat interval tanpa mengubah pokok, bunga, angsuran, atau status pinjaman.

Atur `.env` lokal menjadi `PUMK_SETTLEMENT_TOLERANCE=100000.00`, lalu jalankan `php artisan config:clear`. Nilai lokal `10000.00` mengalahkan default baru bila tidak diubah. Penutupan lama tetap memakai toleransi yang tersimpan saat tindakan. Jangan memakai `migrate:fresh` pada data pengguna.

Tabel `pumk_loan_closures` dibutuhkan sebelum kode baru digunakan. Rollback yang menghapus tabel menghilangkan riwayat buka/tutup; gunakan perbaikan maju setelah ada transaksi baru.

## Alur administrasi

1. Pilih fasilitas yang benar pada Riwayat Fasilitas Pinjaman.
2. Untuk SPJ, identitas, atau jaminan, gunakan **Edit Arsip dan Dokumen**; alasan wajib, status tetap lunas, nilai keuangan tidak dapat diubah.
3. Untuk koreksi transaksi, gunakan **Buka Kembali Pinjaman Lama** dengan alasan. ID, sumber saldo, dan angsuran dipertahankan. Penutupan sebelumnya tersimpan dalam riwayat.
4. Tidak ada aksi **Buat Pinjaman Baru** pada mitra yang sudah ada. Parameter lama `new_loan` ditolak server; edit atau buka kembali tidak menambahkan pinjaman.

Monitoring mengecualikan pinjaman dalam interval lunas sejak tanggal penutupan sampai sebelum tanggal pembukaan kembali (Asia/Jakarta). Riwayat periode saat masih ditutup tidak hilang ketika pinjaman dibuka kembali. Tutup/buka pada hari yang sama mengikuti posisi akhir hari. Snapshot pada atau setelah perubahan ditandai perlu rekonsiliasi. Perubahan angsuran yang mengubah estimasi kolektibilitas juga mencatat riwayat kategori baru.

## Pemeriksaan data yang sudah ada

Jalankan `php artisan pumk:audit-settlements --json` atau tambahkan `--mitra=ID` untuk satu mitra. Perintah hanya membaca data; keluaran memakai ID tanpa nama, nomor identitas, atau catatan pribadi. Exit code 1 menunjukkan temuan yang perlu ditinjau. Periksa kesesuaian status monitoring, metadata, alasan, batas saat penutupan, saldo sumber, dan total kartu terkini. Pemeriksaan ini tidak membuktikan kebenaran bukti pembayaran atau otorisasi bisnis.

Untuk kasus Meubel Wahyu Lestari, bandingkan ID fasilitas aktif dengan fasilitas lunas lama melalui pemilih kartu dan hasil audit. Jika ada fasilitas baru yang kosong, jangan menyalin saldo atau menghapusnya secara otomatis: periksa dahulu transaksi/dokumen yang sudah melekat. Unggah SPJ ke fasilitas lama melalui edit arsip. Patch ini mencegah pengulangan masalah; data lokal lama belum diperbaiki atau diperiksa oleh patch.

## Validasi

`PumkArchiveWorkflowTest` mencakup 11 nominal contoh pengguna (komponen sintetis kecuali contoh Arsya), kedua arah komponen berbeda tanda, saldo kartu terkini setelah perubahan pembayaran impor, saldo -2.143.279, batas inklusif 100.000 dan penolakan 100.000,01, edit SPJ tanpa perubahan saldo, larangan edit finansial arsip, buka/tutup berulang beserta monitoring historis, penolakan aksi fasilitas baru yang dihapus, kepemilikan pinjaman, dan pembaruan kolektibilitas. Jalankan `php artisan test` pada database testing terpisah. Pengujian UI manusia dan konkurensi MySQL tetap perlu dilakukan pada lingkungan yang sesuai.

Perubahan ini belum menyatukan semua rumus kartu dengan resolver historis atau memperbaiki fallback saldo snapshot saat baseline sumber lebih baru dari cutoff. Data yang belum dapat direkonstruksi tetap ditandai unknown, bukan dipaksa lunas.
