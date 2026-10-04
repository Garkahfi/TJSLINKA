# Rekonsiliasi kartu piutang PUMK internal

Pemeriksaan dan perbaikan ini berlaku untuk PUMK internal yang memakai Kartu Piutang, **bukan PUMK BRI**. Posisi keuangan kantor memakai tanggal acuan workbook resmi, bukan tanggal komputer. Audit sumber dan database dilakukan baca-saja; perubahan database hanya berupa metadata presisi sumber dan penyegaran cache pinjaman aktif setelah backup. Tidak ada reimport, pelunasan, pembayaran pengganti, perubahan SPJ, atau penghapusan histori.

## Sumber dan cakupan

- Workbook: `Database PUMK TJSL New (2).xlsx`; sheet `Database new versi baseon SPJ`; tabel `tblRoutineReg4`. SHA-256: `14c466ca76ce62596052e3614fc6b7b4176c31b07c1407e9adb1b29cc97220b4`.
- Tanggal acuan `C5`/`AJ`: **31 Juli 2026**. Ada 394 nomor sumber pada baris 9-402. Nomor di A berada di luar rentang tabel B8:BD402; pembaca tetap mengambil seluruh kolom bisnis hingga CJ.
- Nomor sumber dicocokkan dengan `source_key = SHA256("db-pumk-v1|pinjaman|<nomor>")`, bukan nama mitra. Semuanya cocok dengan 394 pinjaman lokal: 371 aktif dan 23 lunas. Relasi saat audit menunjuk ke 394 ID mitra berbeda; ini hasil data, bukan asumsi model. Pinjaman lokal tambahan ID 395 adalah dummy terverifikasi yang dikecualikan dari rekap; ID 396 nonaktif.
- Aplikasi lokal memakai MySQL `127.0.0.1:3306`, database `tjslinka`. Kredensial dan data pribadi tidak dicantumkan. Sheet lama `Database Saldo Piutang` dan dokumen SPJ bukan sumber angka keuangan.

## Aturan keuangan yang diterapkan

1. Default kartu, kategori/filter daftar, rekap aktif+lunas, dan pratinjau pelunasan pinjaman impor menggunakan tanggal acuan sumber dari `baseline_sumber.formula_sumber.tanggal_acuan` (fallback ke tanggal snapshot impor). Jam komputer, tahun filter histori kartu, dan tanggal penutupan tidak memajukan tanggal acuan tersebut. Laporan historis yang meminta tanggal eksplisit tetap memakai tanggal yang diminta.
2. Formula resmi: `AP=DATEDIF(AH,AJ,"m")+1` bila tanggal valid dan AJ tidak lebih awal dari AH; `AQ=min(AG,AK*AP)` tanpa pembulatan awal; `AR=ROUNDDOWN((AQ-AN)/AK,0)`; `AN=AL+AM`. AR <=1 Lancar, 2-6 Kurang Lancar, 7-9 Diragukan, >=10 Macet. AI adalah akhir kontrak, bukan batas AP. Denda tidak termasuk AN. Jadwal kosong/invalid tidak diubah diam-diam menjadi Lancar.
3. AL/AM efektif berasal dari baseline sumber ditambah **delta bersih** pembayaran lokal yang benar-benar tercatat. Pada posisi kantor, seluruh pembayaran yang relevan dihitung sebagaimana penjumlahan kolom workbook, tanpa cutoff periode berdasarkan C5. Audit historis bertanggal eksplisit tetap memakai batas waktu yang diminta. Saldo awal agregat dan rincian yang merepresentasikan pembayaran sama tidak dihitung dua kali.
4. AU/AV/AW mentah sumber dipertahankan. Setelah delta pembayaran, `AW_efektif = AW_sumber - delta_pokok - delta_bunga`. Perhitungan memakai presisi mentah, lalu pembulatan ke dua desimal **di akhir**. Nominal rekap menjumlah AW efektif mentah per ID pinjaman unik sebelum pembulatan subtotal kategori. Saldo negatif tetap bertanda negatif.
5. Pinjaman aktif dan lunas memakai kalkulasi kartu yang sama untuk rekap. Menandai lunas/reopen adalah perubahan status administratif, bukan pembayaran; snapshot penutupan tetap sebagai histori dan tidak ditulis ulang. Status tidak memaksa kategori menjadi Lancar. Filter status dan pagination tidak membatasi rekap gabungan; pencarian, wilayah, dan sektor tetap membatasinya. Toleransi pelunasan Rp100.000 tidak dipakai sebagai toleransi rekonsiliasi.

## Penerapan database lokal dan hasil audit

Backup sebelum backfill: `storage/backups/pumk-reg4-before-backfill-20261003-184800.sql` (2.191.816 byte; SHA-256 `fd4beec380850183f2d303c51943be4fcf9bb60576b20e286b6e2c9aa352f4d5`). Dump dan footer diverifikasi sebelum perubahan. Untuk pemulihan, tinjau backup tersebut dan gunakan prosedur restore database yang disetujui; tidak ada restore otomatis.

Dry-run mencocokkan 394/394 source key dan input formula. Backfill transaksional menyimpan metadata presisi pada 394 baseline, menyegarkan 85 cache pinjaman aktif yang memang berubah, dan tidak menyentuh 23 cache/snapshot penutupan. Dry-run ulang menunjukkan 0 metadata atau cache yang tersisa untuk diubah. Tidak ada report monitoring tersimpan yang perlu ditandai stale. Sebelumnya 394 baseline tersebut belum menyimpan metadata presisi lengkap.

Perhitungan independen dari input workbook cocok dengan cached `AP`, `AR`, dan `AT` pada 394/394 baris saat C5. `AQ` cached pada nomor sumber 197 dan 199 berbeda hanya di presisi tampilan; formula aplikasi mempertahankan hasil mentah sebelum AR. Nomor 198 memiliki cached `AW=0,22383972`, yang ditampilkan Rp0,22; nilai Rp0,23 sebelumnya berasal dari menjumlah komponen yang sudah dipotong ke sen. Selisih satu sen itu sudah hilang tanpa transaksi koreksi.

| Basis | Lancar | Kurang Lancar | Diragukan | Macet | Total |
| --- | ---: | ---: | ---: | ---: | ---: |
| Workbook resmi pada C5 (jumlah / Rp) | 66 / 340.392.096,12 | 89 / 722.233.183,04 | 66 / 649.402.735,00 | 173 / 2.460.327.493,00 | 394 / 4.172.355.507,16 |
| Rekap aplikasi lokal sesudah perubahan (jumlah / Rp) | 66 / 340.392.096,12 | 89 / 718.899.183,04 | 66 / 649.402.735,00 | 173 / 2.460.327.493,00 | 394 / 4.169.021.507,16 |

Satu-satunya selisih saldo mentah lokal terhadap AW workbook ada pada nomor sumber **323**: sumber Rp186.664.000, kartu Rp183.330.000. Transaksi manual lokal ID **95**, periode Agustus 2026, pokok Rp3.334.000, menyebabkan delta itu. AR sumber 3 menjadi AR lokal 2; keduanya tetap Kurang Lancar. Transaksi impor ID 89-92 sudah tercakup baseline. ID 95 tidak mempunyai nomor/berkas bukti pembayaran; keabsahannya perlu pemeriksaan manusia. Transaksi tidak dihapus maupun dinyatakan sah hanya demi menyamakan rekap.

Pada seluruh 394 baris, tanggal default kartu dan AP sesuai C5/sumber; kategori lokal sesuai AT sumber. AR lokal berbeda hanya untuk nomor 323 karena transaksi ID 95. Saldo mentah lokal berbeda hanya pada nomor yang sama. Jadwal sumber ID 43 mempunyai AI yang tidak valid dan AH tersimpan lokal ID 82 berbeda; metadata formula memakai AH workbook resmi tanpa menimpa kontrak atau SPJ. Nomor 97 tetap Lancar (AR -13), nomor 130 Diragukan (AR 8), nomor 139 Macet (AR 10), dan nomor 198 Lancar dengan saldo Rp0,22.

Monitoring PT INKA mempunyai cakupan operasional/historis sendiri. Ia tidak otomatis harus memiliki jumlah kontributor sama dengan rekap aktif+lunas; parameter tanggal eksplisitnya tetap berlaku. Modul PUMK BRI tidak diubah.

## Verifikasi

Tes otomatis menggunakan SQLite `:memory:` terpisah; pengaman TestCase menolak koneksi testing ke database aplikasi. Pengujian mencakup tanggal komputer yang berubah tanpa mengubah C5, perubahan tanggal resmi pada fixture, pembayaran setelah snapshot, presisi sub-sen, filter/rekap aktif+lunas, dan histori penutupan. Hasil jumlah tes aktual dicatat pada laporan pekerjaan, bukan diasumsikan dari pengujian terdahulu.

Audit dan GET tetap baca-saja. UAT visual dengan akun Admin PUMK masih perlu memastikan detail kartu, filter kategori dan footer, ekspor, serta monitoring historis terlihat sesuai di browser. Jangan menggunakan `migrate:fresh`, pembayaran fiktif, atau perubahan SPJ untuk mengejar subtotal workbook.
