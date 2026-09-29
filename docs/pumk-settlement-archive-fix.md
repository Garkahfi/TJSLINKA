# Pelunasan, arsip, dan pembukaan kembali PUMK

Perubahan ini memisahkan edit administrasi, membuka kembali pinjaman lama, dan membuat fasilitas baru. Implementasi lama membuat fasilitas baru ketika mitra arsip disimpan melalui Edit/Aktifkan Kembali. Pinjaman kosong tersebut dapat terlihat bernilai nol pada kartu meskipun dasar saldonya belum diketahui oleh server.

## Aturan pelunasan

- Pokok dan bunga nol: pelunasan normal.
- Total negatif dengan kedua komponen tidak positif: kelebihan bayar dapat ditutup tanpa batas nominal negatif; wajib catatan dan konfirmasi. Saldo negatif tetap tersimpan, tanpa transaksi refund otomatis.
- Sisa positif sampai dengan Rp100.000: boleh ditutup manual dengan catatan sebagai toleransi selisih. Ini batas yang dipilih untuk pengembangan pada 29 September 2026, bukan pernyataan kebijakan resmi perusahaan. Selisih disimpan tanpa pembayaran fiktif.
- Saldo tidak diketahui, saldo kartu berbeda dari resolver, atau komponen pokok/bunga berlawanan tanda: tetap perlu pemeriksaan sumber.

Cabang kelebihan bayar sudah tersedia sebelum perubahan ini. Contoh dialog dengan alasan server `Kelebihan bayar` berarti pratinjau mengizinkan penutupan; penyebab penolakan POST perlu dilihat dari pesan validasinya. Dialog sekarang dapat digulir, menampilkan syarat, mempertahankan checkbox, dan terbuka lagi bila server menolak.

## Penerapan pada instalasi pengguna

Setelah perubahan ditinjau dan dipasang, jalankan migration biasa `php artisan migrate` pada koneksi yang benar. Migration menyalin metadata penutupan lama ke riwayat interval tanpa mengubah pokok, bunga, angsuran, atau status pinjaman.

Atur `.env` lokal menjadi `PUMK_SETTLEMENT_TOLERANCE=100000.00`, lalu jalankan `php artisan config:clear`. Nilai lokal `10000.00` mengalahkan default baru bila tidak diubah. Penutupan lama tetap memakai toleransi yang tersimpan saat tindakan. Jangan memakai `migrate:fresh` pada data pengguna.

Tabel `pumk_loan_closures` dibutuhkan sebelum kode baru digunakan. Rollback yang menghapus tabel menghilangkan riwayat buka/tutup; gunakan perbaikan maju setelah ada transaksi baru.

## Alur administrasi

1. Pilih fasilitas yang benar pada Riwayat Fasilitas Pinjaman.
2. Untuk SPJ, identitas, atau jaminan, gunakan **Edit Arsip dan Dokumen**; alasan wajib, status tetap lunas, nilai keuangan tidak dapat diubah.
3. Untuk koreksi transaksi, gunakan **Buka Kembali Pinjaman Lama** dengan alasan. ID, sumber saldo, dan angsuran dipertahankan. Penutupan sebelumnya tersimpan dalam riwayat.
4. Gunakan **Buat Pinjaman Baru** hanya untuk penyaluran baru; pokok positif, nilai bunga, dan tanggal pencairan wajib.

Monitoring mengecualikan pinjaman dalam interval lunas sejak tanggal penutupan sampai sebelum tanggal pembukaan kembali (Asia/Jakarta). Riwayat periode saat masih ditutup tidak hilang ketika pinjaman dibuka kembali. Tutup/buka pada hari yang sama mengikuti posisi akhir hari. Snapshot pada atau setelah perubahan ditandai perlu rekonsiliasi. Perubahan angsuran yang mengubah estimasi kolektibilitas juga mencatat riwayat kategori baru.

## Pemeriksaan data yang sudah ada

Jalankan `php artisan pumk:audit-settlements --json` atau tambahkan `--mitra=ID` untuk satu mitra. Perintah hanya membaca data; keluaran memakai ID tanpa nama, nomor identitas, atau catatan pribadi. Exit code 1 menunjukkan temuan yang perlu ditinjau. Periksa kesesuaian status monitoring, metadata, alasan, batas saat penutupan, saldo sumber, dan perbedaan kartu. Pemeriksaan ini tidak membuktikan kebenaran bukti pembayaran atau otorisasi bisnis.

Untuk kasus Meubel Wahyu Lestari, bandingkan ID fasilitas aktif dengan fasilitas lunas lama melalui pemilih kartu dan hasil audit. Jika ada fasilitas baru yang kosong, jangan menyalin saldo atau menghapusnya secara otomatis: periksa dahulu transaksi/dokumen yang sudah melekat. Unggah SPJ ke fasilitas lama melalui edit arsip. Patch ini mencegah pengulangan masalah; data lokal lama belum diperbaiki atau diperiksa oleh patch.

## Validasi

`PumkArchiveWorkflowTest` mencakup saldo -2.143.279, batas inklusif 100.000, edit SPJ tanpa perubahan saldo, larangan edit finansial arsip, buka/tutup berulang beserta monitoring historis, fasilitas baru eksplisit, kepemilikan pinjaman, dan pembaruan kolektibilitas. Jalankan `php artisan test` pada database testing terpisah. Pengujian UI manusia dan konkurensi MySQL tetap perlu dilakukan pada lingkungan yang sesuai.

Perubahan ini belum menyatukan semua rumus kartu dengan resolver historis atau memperbaiki fallback saldo snapshot saat baseline sumber lebih baru dari cutoff. Data yang belum dapat direkonstruksi tetap ditandai unknown, bukan dipaksa lunas.
