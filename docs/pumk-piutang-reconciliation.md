# Rekonsiliasi kartu piutang PUMK

Perubahan ini dibuat dari `codex/pumk-settlement-archive-20260929`, commit
`f4a47976fe7a14503d82ca008998dac53eeb5f6c`. Tidak ada migrasi, pemulihan saldo otomatis,
atau perubahan database pengguna dari pemeriksaan ini.

## Sumber pembanding

Gunakan **Database PUMK TJSL New (2).xlsx**, worksheet **Database new versi baseon SPJ**,
tabel **tblRoutineReg4**, sesuai pembaca importer. SHA-256 file yang diperiksa:

```text
14c466ca76ce62596052e3614fc6b7b4176c31b07c1407e9adb1b29cc97220b4
```

Kolom A adalah nomor urut sumber; AT adalah kategori, AU sisa pokok, AV sisa bunga,
dan AW total sisa pinjaman. Identitas pinjaman dicocokkan melalui `source_key`
yang dibuat importer dari nomor urut, bukan nama mitra. Dua pinjaman dengan nama
mitra sama tetap dihitung terpisah menurut ID pinjaman.

Hasil impor file asli ke **database uji kosong**, tanpa pembayaran tambahan atau
penutupan: 394/394 baris berhasil, 0 gagal, semua pinjaman mempunyai dasar saldo.
Nominal sumber dan laporan per mitra disimpan di luar Git. Pembayaran sesudah
snapshot dan saldo penutupan yang terbukti dapat menyebabkan selisih sah terhadap
Excel, sehingga angka sumber tidak dipaksakan pada database berjalan.

Satu selisih **Rp0,01** ditemukan pada nomor sumber **198**: importer menormalkan
AU dan AV masing-masing ke dua desimal sebelum menjumlahkannya. Hasil komponen
berbeda satu sen dari normalisasi AW. Kebijakan pemotongan desimal importer
tetap digunakan; audit menampilkan `components_minus_aw` agar selisih terlihat.
Jangan mengganti angka atau kebijakan pembulatan hanya untuk menyamakan subtotal.

## Perhitungan yang digunakan

- Untuk pinjaman impor dengan baseline lengkap: sisa pokok saat ini = sisa pokok
  baseline − (pokok masuk saat ini − pokok masuk dalam baseline). Bunga mengikuti
  rumus yang sama. Total kartu = sisa pokok + sisa bunga.
- Perubahan pembayaran adalah perubahan bersih terhadap baseline. Ini mencakup
  penambahan, koreksi, dan penghapusan pembayaran; bukan hanya jumlah pembayaran
  yang baru dibuat. Denda mengikuti mekanisme tunggakan yang sudah ada dan tidak
  ditambahkan ke total sisa pokok + bunga.
- Kolektibilitas sumber tetap digunakan jika pembayaran tidak berubah. Ketika
  pembayaran berubah, mekanisme sistem yang sudah ada menyesuaikan tunggakan;
  bulan tunggakan adalah pembagian yang dibulatkan ke atas. Pembulatan kini memakai
  hasil bagi bulat dan sisa desimal tepat, sehingga satu sen tidak hilang.
- Ambang bulan yang sudah ada tetap: 0–1 Lancar, 2–6 Kurang Lancar, 7–9 Diragukan,
  dan mulai 10 Macet. Perhitungan jadwal pinjaman manual masih merupakan estimasi
  sistem; perubahan ini tidak menetapkan rumus bisnis baru.
- Semua penjumlahan nominal kartu dan total pada event model memakai BCMath pada
  string desimal, tanpa konversi ke `float`.
- Rekap menghitung setiap ID pinjaman aktif (`status=aktif`, `is_active=true`) dan
  lunas sekali. Nominal negatif tetap bertanda negatif. Filter status tab dan
  pagination tidak membatasi rekap; pencarian nama, wilayah, dan sektor membatasinya.
- Saldo aktif dihitung dari komponen kartu yang lengkap, termasuk saat cache kosong.
  Saldo yang benar-benar belum terbukti tidak diganti nol dan ditandai sebagai subtotal.
- Saldo lunas memakai metadata penutupan yang konsisten. Konflik metadata atau nol
  legacy tanpa bukti tetap belum diketahui. Kategori tersimpan pada snapshot
  penutupan baru; kategori lama menggunakan fallback histori yang sudah ada.
  Pelunasan tidak otomatis mengganti Macet menjadi Lancar.

Footer tetap berada di bawah tabel dan hanya muncul setelah kategori dipilih.
Tabel memilih pinjaman yang cocok dengan kategori, termasuk kategori penutupan
yang terbukti, lalu mengambil pinjaman terbaru di antara hasil yang cocok.
Nominal baris mempertahankan sen. Tidak ada panel atau fitur dashboard baru.

## Pemeriksaan database lokal yang masih diperlukan

Database lokal pengguna belum tersedia dalam lingkungan pemeriksaan ini. Dua
kasus berikut belum boleh dinyatakan pulih atau benar sampai bukti lokal diperiksa:

1. **Pinjaman dengan selisih subtotal terhadap sumber**: gunakan nomor urut Excel
   dari kasus yang dilaporkan, bukan pencocokan nama. Jumlah selisih yang sama
   dengan satu angsuran tidak membuktikan adanya pembayaran.
2. **Satu pinjaman Lancar dengan saldo belum tersedia**: identifikasi ID-nya dan
   tentukan apakah cache kosong, komponen kurang, atau penutupan legacy kehilangan
   metadata. Jangan menganggap saldo nol atau memulihkan dari workbook tanpa bukti.

Setelah memasukkan perubahan branch `codex/pumk-reconciliation-20261001` pada
checkout lokal yang sudah menggunakan branch sumber di atas, jalankan dari root
proyek dengan koneksi database lokal yang biasa digunakan aplikasi:

```bash
php artisan pumk:audit-piutang "C:/lokasi/Database PUMK TJSL New (2).xlsx" --json > audit-piutang-lokal.json
```

Ganti path workbook sesuai komputer. Command ini hanya membaca: tidak melakukan
impor, sinkronisasi cache, pelunasan, perubahan kategori, atau backfill.
Output JSON menyertakan ID internal dan bukti nominal tanpa KTP, rekening,
nama mitra, lokasi dokumen, atau catatan pribadi. Simpan laporan di luar Git.

Untuk membatasi satu mitra, gunakan ID database dari laporan pertama:

```bash
php artisan pumk:audit-piutang "C:/lokasi/Database PUMK TJSL New (2).xlsx" --mitra=123 --json
```

`123` adalah contoh, bukan ID kasus sebenarnya. Audit per mitra tidak melaporkan baris
sumber lain sebagai data hilang.

Berikan tugas berikut kepada Codex lokal:

> Jalankan audit baca-saja di atas setelah membawa perubahan branch rekonsiliasi.
> Temukan pinjaman dengan nomor urut sumber pada kasus yang dilaporkan dan setiap pinjaman dengan
> `recap_category=lancar`, `included_in_recap=true`, `recap_total=null`.
> Bandingkan `source`, `baseline`, `net_payment_change_from_baseline`, `payments`,
> `cached_total`, `card_total`, `recap_total`, `closing_total`, dan `closure_total`.
> Jelaskan selisih yang dilaporkan dengan bukti ID pembayaran atau koreksi yang sah;
> jumlah yang kebetulan sama dengan angsuran bukan bukti pembayaran.
> Untuk saldo belum tersedia, tunjukkan data yang hilang atau konflik beserta
> sumber pemulihan yang dapat diverifikasi. Jangan menjalankan UPDATE, reimport,
> sinkronisasi massal, atau menetapkan nol untuk menyamakan Excel.
> Laporkan subtotal empat kategori, jumlah pinjaman yang dihitung/tidak dihitung,
> ID yang belum terbukti, dan sebab setiap selisih. Jika ada pembayaran yang benar,
> selisih sumber dan web harus dipertahankan serta dijelaskan.

Sesudah bukti lokal tersedia, pemulihan data dilakukan per ID yang terbukti dengan
backup dan pencatatan audit. Perubahan kode ini tidak menebak saldo penutupan lama.

## Validasi

Jalankan regresi keuangan dalam lingkungan pengujian yang terisolasi:

```bash
php artisan test --filter='PumkPiutangCalculatorTest|KartuPiutangServiceTest|PumkCollectibilitySummaryTest|PumkPiutangReconciliationTest|PumkImportServiceTest|PumkMitraManagementTest|PumkArchiveWorkflowTest|PumkLoanCompletionTest|PumkInternalMonitoringYearTest'
```

Jangan menggunakan `migrate:fresh` pada database aplikasi. TestCase proyek menolak
pengujian yang tidak menggunakan `testing`, SQLite, dan `:memory:`.

Regresi mencakup desimal besar, satu sen di batas kolektibilitas, cache kosong,
dua pinjaman milik satu mitra, saldo negatif, aktif + lunas, konflik penutupan,
kategori penutupan, pelunasan toleransi Rp100.000, buka kembali arsip, histori
pembayaran, reimport, serta monitoring. Pembacaan rekap dan audit tidak menulis data.

Suite penuh branch asal juga diperiksa: 277 lulus dan 12 gagal, pada 9 test
`MonitoringUploadChoiceTest` dan 3 test `PumkBriTemplateDownloadTest` dengan error
pembacaan XLSX. Kegagalan yang sama tetap ada setelah perubahan ini; penyelesaian
bug upload BRI/TJSL tersebut berada di luar perubahan logika kartu piutang ini.
