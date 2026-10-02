# Rekonsiliasi kartu piutang PUMK

Pemeriksaan lanjutan dilakukan pada checkout lokal 2 Oktober 2026. Audit awal
dilakukan baca-saja; tes mutasi memakai SQLite `:memory:` terpisah. Sesudah
backup dan dry-run, metadata rumus sumber dibackfill pada database lokal.
Tidak ada reimport, pelunasan, pembayaran pengganti, perubahan berkas SPJ,
atau perubahan saldo asli dari pekerjaan ini.

## Sumber resmi dan identitas

Satu-satunya sumber keuangan adalah `Database PUMK TJSL New (2).xlsx`, sheet
`Database new versi baseon SPJ` (`tblRoutineReg4`), SHA-256
`14c466ca76ce62596052e3614fc6b7b4176c31b07c1407e9adb1b29cc97220b4`.
Tanggal acuan `C5` adalah 31 Juli 2026. Ada 394 baris sumber (9–402). Kolom A
dicocokkan ke `source_key = SHA256("db-pumk-v1|pinjaman|<nomor>")`, bukan nama
mitra. Dokumen SPJ hanya berkas pendukung: nomor, tanggal, isi, dan waktu
unggahnya tidak menentukan angka keuangan atau kolektibilitas.

## Rumus kartu dan rekap

- Snapshot impor menyimpan sisa pokok `AU`, sisa bunga `AV`, pembayaran pokok
  `AL`, dan bunga `AM`. Untuk posisi berjalan, tiap sisa dikurangi **delta
  bersih** pembayaran komponen tersebut dari baseline. Saldo awal adalah
  agregat pembayaran historis; transaksi rinci periode yang sama tidak boleh
  dihitung dua kali. Edit naik/turun, refresh, dan reimport harus idempoten.
- `AP=DATEDIF(AH,AJ,"m")+1`, `AQ=min(AG,AK×AP)`,
  `AR=ROUNDDOWN((AQ−AN)/AK,0)`, `AN=AL+AM`. `AI` adalah akhir kontrak,
  bukan batas AP. Pada tanggal berjalan AP/AQ bertambah sesuai AH, lalu
  pembayaran pokok+bunga yang sah mengubah AN. Denda tidak termasuk AN.
- Tunggakan mentah berasal dari `AQ−AN`, **bukan** `AS` yang sudah dipotong
  ke bulan penuh. `ROUNDDOWN` memotong menuju nol, termasuk untuk nilai
  negatif. AR ≤1 Lancar, 2–6 Kurang Lancar, 7–9 Diragukan, ≥10 Macet.
  Input jadwal tidak lengkap menghasilkan `belum_dinilai`, bukan Lancar.
- AK sumber dapat mempunyai pecahan sub-sen. Importer dan backfill lokal
  `baseline_sumber.formula_sumber` mempertahankan AK mentah, AH, AG, AP, AQ,
  dan AN untuk perhitungan/pemeriksaan. Pembulatan ke sen dilakukan **sesudah**
  perkalian AK×AP. Backfill hanya menambah metadata sumber, lalu menyegarkan
  cache pinjaman aktif; snapshot penutupan pinjaman lunas tetap dipertahankan.
- Nominal rekap adalah `AU+AV` setelah delta pembayaran, bukan nilai AS atau
  tunggakan. Setiap ID pinjaman aktif/lunas dihitung sekali. Penutupan
  administratif mempertahankan saldo signed dan kategori saat penutupan;
  penutupan bukan pembayaran. Filter status dan pagination tidak membatasi
  rekap gabungan; pencarian, wilayah, dan sektor tetap membatasinya.

## Audit baca-saja database lokal

Koneksi efektif: MySQL `127.0.0.1:3306`, database `tjslinka`, tanpa menampilkan
kredensial. AP, AQ, AR, dan AT dari rumus di atas cocok dengan cached value
pada **394/394** baris resmi di tanggal snapshot. ID 97 tetap Lancar (AR −13),
ID 323 Kurang Lancar, dan ID 325 Macet.

Seluruh 394 nomor sumber cocok dengan ID pinjaman lokal: 371 aktif dan 23
lunas. Pada saat audit, relasinya menunjuk ke 394 ID mitra berbeda; ini hasil
database, bukan asumsi satu baris Excel selalu satu mitra. Dua pinjaman lokal
di luar workbook adalah dummy terverifikasi ID 395 (dikecualikan dari rekap
berdasarkan source key) dan ID 396 nonaktif. Tidak ada saldo dalam cakupan
rekap yang tak diketahui pada audit ini.

Sebelum backfill, **394/394 baseline lokal belum menyimpan `formula_sumber`**.
Dua jadwal tersimpan berbeda dari file resmi: AI sumber ID 43 tidak valid,
sementara AH lokal ID 82 berbeda. AI tidak memengaruhi AP. Metadata rumus
sekarang memakai AH dari sheet resmi tanpa menimpa field kontrak AI atau
berkas pendukung.

Backup pra-perubahan telah diverifikasi di
`storage/backups/pumk-reg4-before-backfill-20261002-021345.sql` (2.084.208
byte, SHA-256 `91b09f646066d84ae3d21259ad2ff57a42d0276fa382fb94d4bef4b3a3ba6783`).
Dry-run 394/394 cocok, tanpa proyeksi perubahan saldo. Backfill transaksional
menambahkan metadata pada 394 baseline; cache 371 pinjaman aktif disegarkan.
Perubahan cache kategori terjadi pada 15 ID, cache tunggakan pada 83 ID,
sedangkan 23 pinjaman lunas mempertahankan snapshot penutupan. Tidak ada
report monitoring tersimpan yang perlu ditandai stale saat penerapan.
Audit sesudah membuktikan **nol selisih AP/AQ/AR/AT maupun saldo komponen**
antara kartu lokal dan sheet resmi pada tanggal snapshot untuk seluruh 394 ID.
Cache saldo dan kategori aktif juga konsisten dengan kalkulator berjalan.

| Basis | Lancar | Kurang Lancar | Diragukan | Macet |
| --- | ---: | ---: | ---: | ---: |
| Snapshot sumber 31 Juli (jumlah / AW dinormalisasi) | 66 / Rp340.392.096,12 | 89 / Rp722.233.183,04 | 66 / Rp649.402.735,00 | 173 / Rp2.460.327.493,00 |
| Rekap lokal sebelumnya, kategori snapshot / saldo berjalan | 66 / Rp340.392.096,13 | 89 / Rp718.899.183,04 | 66 / Rp649.402.735,00 | 173 / Rp2.460.327.493,00 |
| Rekap lokal sesudah rumus berjalan dan backfill (jumlah / saldo) | 62 / Rp253.651.707,13 | 90 / Rp775.944.305,00 | 61 / Rp531.059.231,04 | 181 / Rp2.608.366.264,00 |

Sebanyak 15 ID berbeda kategori dari snapshot Juli pada posisi berjalan.
Nomor sumbernya: 9, 27, 77, 92, 106, 114, 130, 137, 154, 156, 165,
174, 176, 194, dan 199. Total rekap lokal sebelum dan sesudah tetap
Rp4.169.021.507,17; perubahan pada baris kategori adalah perpindahan
kelompok, bukan penciptaan saldo.
Kategori dapat berubah karena waktu/pembayaran; kedua baris tabel tidak boleh
disamakan tanpa menyetarakan tanggal, transaksi, cakupan, dan presisi.
Selisih saldo terhadap AW sumber hanya pada dua ID:

- ID 323: sumber Rp186.664.000, kartu Rp183.330.000. Delta pokok
  Rp3.334.000 berasal dari transaksi manual ID 95 periode Agustus; transaksi
  impor ID 89–92 sudah tercakup baseline. ID 95 tidak berisi nomor maupun
  berkas bukti pembayaran; validitasnya perlu diperiksa manusia. Tidak diubah.
- ID 198: komponen tersimpan menjumlah Rp0,23, cached AW yang dinormalisasi
  Rp0,22. Selisih Rp0,01 adalah normalisasi komponen, bukan transaksi.

Monitoring PT INKA memiliki cakupan operasional tersendiri dan tidak wajib
sama dengan rekap aktif+lunas. Audit layanan lokal tahun 2026 berstatus
`available` per 1 Oktober 2026: 371 pinjaman diketahui, 24 ditutup dalam
cakupan monitoring, nol tidak diketahui, dan klasifikasi tidak terbatas.
Histori 2025 yang parsial tidak boleh dianggap bernilai nol. Endpoint
`/monitoring/pumk-inka` aktif tetapi mengalihkan tamu ke login; UAT visual
dengan akun berwenang tetap perlu dilakukan.

## Validasi dan langkah lanjut

Tes keuangan dijalankan pada SQLite `:memory:`; TestCase menolak koneksi
testing yang memakai database aplikasi. Audit workbook dan database utama
sebelum/sesudah dilakukan baca-saja. Regresi terkait: **126 tes dan 1.125
asersi lulus**; keseluruhan suite: **312 tes dan 3.551 asersi lulus**.
UAT visual dengan akun berwenang tetap dilaporkan terpisah.
Jangan menjalankan `migrate:fresh`, membuat pembayaran fiktif, atau mengubah
dokumen SPJ untuk menyamakan angka.
