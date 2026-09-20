# Analisis dan Pemetaan Database PUMK

Dokumen ini menjadi acuan pemetaan workbook final V2 `Database PUMK TJSL New (2).xlsx` ke database PUMK Internal/Kartu Piutang di TJSLINKA. Workbook dianalisis secara baca-saja; nilai KTP, nomor telepon, nomor rekening, nama pemilik, dan alamat tidak dicantumkan di dokumen maupun log impor. Dataset ini terpisah dari PUMK BRI dan tidak mengubah tabel `pumk_bri_*`.

## 1. Sumber final yang terverifikasi

| Atribut | Hasil verifikasi |
|---|---|
| Nama file | `Database PUMK TJSL New (2).xlsx` |
| Worksheet | `Database new versi baseon SPJ` |
| Header kolom | Baris 8 |
| Data | Baris 9-402, sebanyak 394 baris |
| Rentang bisnis terisi | `A:CK`, 89 kolom bisnis |
| Perubahan struktur | `G` adalah Reschedule Ke-4; `CK` adalah Total Angsuran Desember |
| Tanggal acuan `C5` | 31 Juli 2026 (`2026-07-31`) |
| SHA-256 | `14c466ca76ce62596052e3614fc6b7b4176c31b07c1407e9adb1b29cc97220b4` |
| Wilayah setelah normalisasi | 17 |
| Sektor yang dipakai | 11 |

Workbook V2 menambahkan kolom Reschedule Ke-4 pada `G`, sehingga seluruh mapping lama mulai identitas Mitra bergeser satu kolom ke kanan. Importer membaca sampai `CK`; kolom `CK` tidak lagi dianggap kosong.
    
Workbook mengandung 11.134 sel formula. Importer membaca cached value, menyimpannya sebagai snapshot historis, lalu membandingkan sebagian nilai yang sudah memiliki padanan kalkulator aplikasi. Satu formula pada `F192` tidak memiliki cached value; baris tersebut ditandai `needs_review` dan nilai lama tidak dihapus. File sumber tidak diubah.

## 2. Pemetaan kolom final

### 2.1 Nomor, SPJ, reschedule, dan identitas (`A:O`)

| Excel | Kelompok data | Target utama | Perlakuan |
|---|---|---|---|
| A | Nomor urut sumber | `pumk_pinjaman.no_urut_sumber`, pembentuk `source_key` | Harus unik dalam format sumber; `pumk_import_rows.source_row_number` mencatat nomor baris worksheet secara terpisah |
| B | Nama Mitra Binaan | `pumk_mitra.nama_mitra` | Normalisasi spasi; wajib |
| C | SPJ awal | `pumk_pinjaman.spj_awal` | Nullable |
| D | Reschedule ke-1 | `pumk_pinjaman.reschedule_ke1` | Nullable |
| E | Reschedule ke-2 | `pumk_pinjaman.reschedule_ke2` | Nullable |
| F | Reschedule ke-3 | `pumk_pinjaman.reschedule_ke3` | Nullable |
| G | Reschedule ke-4 | `pumk_pinjaman.reschedule_ke4` | Nullable; ditambahkan dengan migration backward-compatible |
| H | Jenis usaha | `pumk_mitra.jenis_usaha` | Nullable |
| I | Sektor usaha | `pumk_sektor_usaha` dan `pumk_mitra.sektor_sumber` | Lookup terkontrol; 11 sektor dipakai |
| J | Alamat | `pumk_mitra.alamat` | Data terbatas |
| K | Wilayah | `pumk_wilayah` dan `pumk_mitra.wilayah_sumber` | Dinormalisasi; 17 wilayah |
| L | Nama pemilik | `pumk_mitra.nama_pemilik` | Data terbatas |
| M | No. KTP | `pumk_mitra.no_ktp_encrypted` | Encrypted cast; nullable |
| N | No. telepon | `pumk_mitra.no_telepon_encrypted` | Encrypted cast; nullable |
| O | No. rekening | `pumk_mitra.no_rekening_encrypted` | Encrypted cast; nullable |

### 2.2 Jaminan dan berkas (`P:AA`)

| Excel | Target database | Nilai terisi pada file final |
|---|---|---:|
| P | `jenis_jaminan` | Nilai sumber terbaru |
| Q | `jaminan_no_pol` | Nilai sumber terbaru |
| R | `jaminan_no_bpkb` | Nilai sumber terbaru |
| S | `jaminan_merk` | Nilai sumber terbaru |
| T | `jaminan_type` | Nilai sumber terbaru |
| U | `jaminan_tahun_kendaraan` | Nilai sumber terbaru |
| V | `jaminan_no_sertifikat` | Nilai sumber terbaru |
| W | `jaminan_luas` | Nilai sumber terbaru |
| X | `jaminan_atas_nama` | Nilai sumber terbaru |
| Y | `jaminan_alamat` | Nilai sumber terbaru |
| Z | `berkas_spj_path` | Nullable; sumber kosong tidak menghapus file manual |
| AA | `berkas_jaminan_path` | Nullable; sumber kosong tidak menghapus file manual |

Kolom Z dan AA tetap tersedia sebagai path file untuk pengisian melalui aplikasi. Import tidak mengarang path, membuat file kosong, atau menghapus file manual ketika sumber kosong.

Workbook V2 memuat satu nilai Reschedule Ke-4 yang berhasil disimpan.

### 2.3 Kontrak pinjaman (`AB:AK`)

Kelompok ini dipetakan ke `pumk_pinjaman` dan mencakup tanggal/tahun pencairan, pokok, bunga, total pinjaman, awal dan akhir angsuran, serta nilai angsuran bulanan. Field `tahun_pencairan` disediakan tersendiri karena ada pada sumber final, tetapi tanggal pencairan tetap menjadi referensi utama ketika tersedia. Nilai tanggal/tahun tidak valid ditandai untuk review, bukan diperbaiki diam-diam.

Satu mitra dapat memiliki lebih dari satu pinjaman, sehingga data mitra dan kontrak tetap dipisahkan. Importer tidak menggabungkan record hanya berdasarkan nama.

### 2.4 Snapshot validasi keuangan (`AL:AW`)

Kelompok ini berisi total angsuran masuk, jatuh tempo/tunggakan, kolektibilitas, dan sisa pinjaman dari workbook. Nilai tersebut disimpan sebagai baseline historis sumber, bukan langsung diganti oleh rumus estimasi aplikasi.

Perbandingan kalkulator yang sudah aman dan terverifikasi baru meliputi:

- `AL`: total pokok masuk;
- `AM`: total bunga masuk;
- `AN`: total angsuran masuk (pokok + bunga).

Pada batch V2, 394 baris dibandingkan. Sebanyak 78 baris mempunyai perbedaan kalkulator pada total pokok/total angsuran dan tetap memakai baseline Excel; perbedaan hanya dicatat untuk review, tidak ditimpa otomatis. Kolom lain di `AO:AW` tetap divalidasi struktur/totalnya dan dipertahankan sebagai snapshot sampai aturan bisnis resminya disahkan.

### 2.5 Saldo awal (`AX:BA`)

Saldo awal disimpan di `pumk_saldo_awal` dengan `cutoff_date` 31 Desember 2025. Empat kolom ini memuat pokok, bunga, denda, dan total saldo angsuran sebelum transaksi bulanan 2026. Total sumber dipakai untuk validasi; komponen saldo disimpan agar perhitungan dapat diaudit.

### 2.6 Angsuran bulanan (`BB:CK`)

Terdapat 12 kelompok bulan, masing-masing tiga kolom: pokok, bunga, dan total angsuran. Periode Januari-Desember ditentukan dari tahun tanggal acuan workbook, bukan hardcode nama file.

Transaksi bermakna disimpan sebagai baris di `pumk_angsuran` dengan unique key `(pinjaman_id, periode)`. Sel kosong/nol tidak dibuat menjadi riwayat transaksi palsu.

## 3. Struktur database yang digunakan

### `pumk_import_batches`

- Audit satu proses impor: `nama_file`, `file_hash`, `tanggal_acuan`, status, total, berhasil, gagal, pengguna pengimpor, waktu mulai/selesai, dan ringkasan aman.
- `file_hash` unik mencegah file identik diimpor dua kali.

### `pumk_import_rows`

- Audit per baris: batch, nomor baris sumber, `row_hash`, status, serta pesan review tanpa PII.
- Warning kualitas data dicatat sebagai `needs_review`; warning bukan kegagalan impor.

### `pumk_wilayah` dan `pumk_sektor_usaha`

- Lookup ternormalisasi dengan slug unik dan status aktif.
- File final menghasilkan 17 wilayah ternormalisasi dan memakai 11 sektor.

### `pumk_mitra`

- Menyimpan identitas, klasifikasi, referensi wilayah/sektor, `source_key`, status aktif, dan audit pembuat.
- KTP, telepon, dan rekening disimpan terenkripsi. Hash KTP hanya digunakan bila perlu untuk pencocokan, bukan untuk ditampilkan.

### `pumk_pinjaman`

- Menyimpan kontrak, empat reschedule, data jaminan, path berkas, tanggal/tahun pencairan, nilai pinjaman, tunggakan, kolektibilitas, sisa pinjaman, `source_key`, status aktif, dan `source_updated_at`.
- `source_updated_at` memakai tanggal acuan sumber (`2026-07-31` akhir hari), bukan waktu proses impor.
- Siklus penyelesaian memakai `status` (`aktif`, `lunas`, atau `nonaktif`) serta metadata `lunas_at`, `lunas_by`, dan `lunas_note`. Pinjaman lunas tidak dihapus sehingga kartu dan histori angsurannya tetap dapat diaudit serta diunduh.

### `pumk_saldo_awal`

- Satu saldo awal per pinjaman dengan cutoff, pokok, bunga, denda, serta batch sumber.
- Tabel ini wajib karena transaksi bulanan 2026 tidak cukup untuk merekonstruksi histori sebelum 2026.

### `pumk_angsuran`

- Satu baris per pinjaman/periode, berisi pokok, bunga, denda, total, batch sumber, dan pembuat manual bila ada.
- Constraint unik `(pinjaman_id, periode)` mencegah duplikasi bulan.

### `pumk_activity_logs`

- Audit aman aktivitas Admin PUMK seperti tambah/perbarui pinjaman, tambah/perbarui angsuran, pelunasan, aktivasi kembali Mitra, serta input RKA/realisasi PUMK BRI.
- Log hanya menyimpan deskripsi dan metadata operasional yang aman; KTP, telepon, rekening, dan alamat tidak disalin ke log.

## 4. Relasi utama

```text
pumk_wilayah          1 --- * pumk_mitra
pumk_sektor_usaha     1 --- * pumk_mitra
pumk_mitra            1 --- * pumk_pinjaman
pumk_pinjaman         1 --- 1 pumk_saldo_awal
pumk_pinjaman         1 --- * pumk_angsuran
pumk_import_batches   1 --- * pumk_import_rows
pumk_import_batches   1 --- * pumk_saldo_awal
pumk_import_batches   1 --- * pumk_angsuran
users                 1 --- * pumk_import_batches
```

## 5. Aturan upsert dan perlindungan data manual

1. `file_hash` mengidentifikasi versi file. File identik ditolak tanpa mengubah database.
2. `source_key` dibentuk stabil dari jenis record dan nomor urut sumber. Sebanyak 394 key workbook V2 cocok dengan baseline, sehingga batch V2 memperbarui 394 record yang sama dan tidak membuat duplikat.
3. Sanity check membandingkan profil identitas dan kontrak sebelum update. Koreksi kontrak diterima bila profil identitas konsisten dan dicatat sebagai `source_contract_corrected`; perubahan profil identitas yang material bersama perubahan kontrak ditolak sebagai `source_profile_conflict`.
4. Saat record sudah ada, nilai sumber yang kosong tidak menimpa nilai database yang telah terisi. Ini melindungi field yang sebelumnya dilengkapi Admin PUMK melalui form.
5. Nilai nonkosong dari file revisi tetap dapat memperbarui snapshot sumber lama.
6. Record impor yang hilang dari file baru hanya dinonaktifkan setelah seluruh file memiliki cakupan aman dan tidak ada baris gagal. Record manual tidak ikut dinonaktifkan.
7. Angsuran sumber direkonsiliasi per bulan. Bila transaksi yang dahulu berasal dari import kini kosong/nol, hanya baris import lama itu yang dihapus.
8. Angsuran manual (`batch_id` kosong dan `created_by` terisi) tidak ditimpa atau dihapus oleh re-import. Konflik periode ditandai sebagai warning untuk review.
9. Nilai snapshot Excel tetap baseline. Kalkulator hanya membandingkan nilai yang sudah tervalidasi dan tidak menimpa data historis secara otomatis.
10. Re-import tidak boleh mengaktifkan kembali pinjaman yang sudah ditandai `lunas` oleh Admin PUMK. Konflik tersebut dicatat sebagai `manual_paid_status_conflict` untuk review, sementara status dan metadata pelunasan manual dipertahankan.

## 6. Semantik tanggal snapshot dan kalkulator

Tanggal acuan `C5` adalah batas snapshot data sumber. Dengan menyimpan `source_updated_at = 2026-07-31`, sistem dapat membedakan data yang sudah tercakup workbook dari angsuran manual yang dicatat setelah snapshot.

Untuk pinjaman hasil impor:

- sisa, tunggakan, dan kolektibilitas mengikuti baseline workbook;
- angsuran manual setelah cutoff tetap mengurangi baseline tersebut di tampilan/perhitungan;
- cache historis tidak ditimpa rumus estimasi kecuali ada tindakan eksplisit;
- pinjaman manual yang tidak memiliki snapshot sumber memakai estimasi sistem yang transparan.

## 7. Hasil import produksi V2

Import file terbaru tercatat sebagai batch produksi ID 8.

| Pemeriksaan | Hasil |
|---|---:|
| Baris sumber | 394 |
| Berhasil / gagal | 394 / 0 |
| Record baru | 0 |
| Record diperbarui | 394 |
| Record dinonaktifkan | 0 |
| Baris `needs_review` | 352 |
| Perbandingan kalkulator AL-AM-AN | 394 diperiksa, 78 berbeda |
| Wilayah / sektor | 17 / 11 |
| Angsuran manual sebelum / sesudah | 1 / 1, digest identik |
| Duplikasi source key Mitra / Pinjaman | 0 / 0 |
| Reschedule Ke-4 terisi | 1 |
| Masalah rekonsiliasi AX:CK | 0 |

Status `needs_review` berarti sumber memiliki field kosong, tanggal anomali, nilai negatif, koreksi kontrak, cached formula yang tidak tersedia, selisih total sumber, atau selisih dengan kalkulator. Seluruh 394 baris tetap berhasil di-upsert; 352 warning tersebut bukan baris gagal dan nilai baseline sumber tidak diganti oleh hasil kalkulator.

## 8. Backup sebelum perubahan

Backup MySQL dibuat dan diverifikasi sebelum migration serta import V2:

- path: `storage/backups/tjslinka_before_pumk_v2_20260918_002449.sql`;
- ukuran: 1.595.013 byte;
- SHA-256: `0FB13C8E32DB65A6816A1189B17D464208C30007FC400264DA4AECCCABC6A782`.

Backup memuat struktur dan data PUMK sebelum penerapan workbook final dan harus dipertahankan sebagai titik pemulihan.

## 9. Import snapshot bulanan PUMK BRI

Dataset `Rekap BRI_format baru (Jan - Agst).xlsx` adalah sumber terpisah dari Kartu Piutang PUMK internal. Data ini tidak digabung ke `pumk_mitra`, `pumk_pinjaman`, atau `pumk_angsuran`. Modelnya juga tidak boleh menyamakan satu baris Excel dengan satu mitra secara permanen: satu Mitra dapat mempunyai satu atau lebih fasilitas pinjaman dan tiap fasilitas dapat mempunyai banyak snapshot bulanan.

### 9.1 Hasil verifikasi workbook

| Atribut | Hasil |
|---|---|
| Periode file awal | Januari-Agustus 2026 |
| Kelanjutan data | Sheet berikutnya dapat memakai tahun default 2026 atau tahun eksplisit, termasuk 2027 |
| Posisi header | Baris 3 untuk Jan-Apr; baris 2 untuk Mei-Agst |
| Total snapshot sumber awal | 925 |
| Identitas mitra final | Ditentukan melalui pencocokan profil multi-atribut dan review kasus ambigu; bukan target angka tetap |
| Tanda `*` pada nama | Dipertahankan persis pada data sumber snapshot; tidak dihapus otomatis |
| Angka ringkasan | Total saldo piutang; wajib sama dengan hasil penjumlahan baris |
| SHA-256 workbook | `5DBAE6936691219C0F6EED7AA51C69B968631763EBC913D30166ACCD563D4D13` |

Importer mencari header secara dinamis sehingga perbedaan posisi baris antarsheet tidak menjadi masalah. Kolom tanggal pencairan dan jatuh tempo tidak lagi diwajibkan atau dipakai untuk menentukan identitas, fasilitas, maupun realisasi penyaluran. Bila tersedia, nilai mentahnya tetap berada di `source_payload` snapshot untuk audit. Kolektibilitas dinormalisasi menjadi `L`, `KL`, `D`, dan `M` beserta label lengkapnya. Perbedaan tanda `*` dengan tidak adanya tanda tersebut tidak ditebak otomatis pada data baru; alias hanya dipakai untuk menghubungkan snapshot legacy yang memang sebelumnya kehilangan tanda dan tetap harus cocok pada profil usaha lainnya.

### 9.2 Struktur identitas dan histori sumber

`pumk_bri_mitra` adalah entitas Mitra Binaan. ID tabel ini adalah surrogate key aplikasi, bukan natural key yang dibentuk dari nama atau tanggal tertentu.

`pumk_bri_fasilitas` menyimpan fasilitas/pinjaman yang dimiliki mitra, termasuk nilai pinjaman, tenor, sektor, status `aktif/lunas`, `first_seen_period`, `last_seen_period`, dan `reference_key` teknis. Tanggal pencairan lama dibiarkan untuk kompatibilitas skema, tetapi tidak dipakai oleh matcher atau dashboard. Fasilitas dipisahkan dari mitra agar satu mitra dapat memiliki lebih dari satu pinjaman tanpa menduplikasi identitas orang/usaha.

`pumk_bri_snapshot_bulanan` menyimpan kondisi suatu fasilitas pada satu periode. Selain saldo dan kolektibilitas, snapshot menyimpan profil sumber periode tersebut: nama mitra, alamat, wilayah, sektor usaha, pinjaman, tenor, serta seluruh nilai mentah pada `source_payload`. Karena itu perubahan alamat atau koreksi sumber pada bulan lain tidak menghapus jejak nilai sebelumnya. Constraint uniknya adalah `(fasilitas_id, bulan, tahun)`.

`pumk_bri_rka_tahunan` menyimpan satu RKA resmi per tahun. `pumk_bri_penyaluran_bulanan` menyimpan input realisasi per tahun dan bulan. Record bulanan hanya dibuat saat Admin PUMK menyimpan nilai; angka `0` berarti sudah diinput nol, sedangkan ketiadaan record berarti belum diinput.

`pumk_bri_identity_reviews` menyimpan kasus yang belum aman untuk diputuskan otomatis: profil sumber, fingerprint, kandidat fasilitas, alasan, status, dan keputusan reviewer.

Relasi khusus dataset ini:

```text
pumk_bri_mitra 1 --- * pumk_bri_fasilitas 1 --- * pumk_bri_snapshot_bulanan
pumk_bri_identity_reviews --- catatan review source row dan kandidat Mitra/Fasilitas
```

### 9.3 Pencocokan multi-atribut dan penanganan ambiguitas

Identitas tidak pernah dikunci menggunakan tanggal pencairan/jatuh tempo atau satu atribut tertentu. Importer menjalankan dua tahap: mengidentifikasi Mitra, lalu mengidentifikasi fasilitas milik Mitra tersebut. Profil yang dibandingkan terutama:

- nama mitra;
- nama, wilayah, dan alamat sebagai konteks profil;
- sektor usaha;
- nominal pinjaman dan tenor;
- atribut konsisten lain yang tersedia pada sumber.

Hasil pencocokan mengikuti aturan berikut:

1. Profil nama, wilayah, pinjaman, tenor, dan sektor yang konsisten di banyak bulan ditautkan ke Mitra dan Fasilitas yang sama. Variasi/typo alamat saja tidak memecah identitas; alamat master pertama dipertahankan dan alamat mentah tiap bulan tetap ada pada snapshot.
2. Nama yang sama tetapi profilnya berbeda jelas dan konsisten—terutama wilayah, nominal pinjaman, tenor, dan sektor—dipandang sebagai Mitra/Fasilitas yang berbeda. Kasus dua nama `mariyat*` tidak boleh otomatis dilebur hanya karena namanya mirip.
3. Tanggal pencairan dan tanggal jatuh tempo dikeluarkan sepenuhnya dari fingerprint pencocokan serta pengelompokan review.
4. Bila perubahan belum jelas merupakan koreksi data atau entitas yang benar-benar berbeda, importer tidak melakukan auto-merge maupun auto-split. Periode tersebut ditahan dan dibuatkan `pumk_bri_identity_reviews` untuk validasi manual.

Fingerprint profil hanya dipakai untuk mendeteksi apakah sebuah keputusan review masih sesuai dengan sumber yang sama; fingerprint bukan natural key Mitra. Setelah diverifikasi, keputusan dapat dicatat dengan salah satu pilihan berikut lalu workbook diimpor ulang dengan `--force`:

```bash
php artisan pumkbri:review-identities --year=2026
php artisan pumkbri:resolve-identity REVIEW_ID --fasilitas=FASILITAS_ID
php artisan pumkbri:resolve-identity REVIEW_ID --fasilitas=FASILITAS_ID --same-profile
php artisan pumkbri:resolve-identity REVIEW_ID --mitra=MITRA_ID
php artisan pumkbri:resolve-identity REVIEW_ID --new-mitra
```

Perintah `pumkbri:review-identities` hanya menampilkan kelompok profil untuk memudahkan pemeriksaan dan tidak membuat keputusan apa pun. Opsi `--detail` menampilkan setiap ID review dan baris sumber. Pengelompokan mengecualikan alamat serta kedua tanggal; opsi `--same-profile` hanya menerapkan keputusan reviewer ke review `pending` dengan profil usaha dan kandidat fasilitas yang sama pada tahun yang sama, bukan memutuskan identitas secara otomatis.

Jika profil sumber berubah setelah review diputuskan, keputusan lama harus divalidasi lagi.

### 9.4 Cara import dan aturan pengamanan

```bash
php artisan pumkbri:import "path/file.xlsx" --year=2026
```

- `--year` wajib dan menjadi tahun untuk nama sheet yang hanya berisi bulan.
- Bila nama sheet memuat tahun, misalnya `Jan 2027`, tahun eksplisit tersebut yang dipakai.
- Periode yang sudah ada dilewati supaya command aman dijalankan ulang setelah sheet baru ditambahkan.
- `--force` mengganti snapshot periode terkait dalam satu transaksi, tetapi tidak diterapkan apabila periode tersebut masih memiliki kasus identitas ambigu yang belum diputuskan.
- Kesalahan atau review identitas pada satu sheet tidak membatalkan sheet lain yang valid.
- Ringkasan saldo harus cocok dengan jumlah seluruh baris sebelum data sheet disimpan.
- Nilai kosong dari file tidak menghapus nilai sumber/histori yang telah tersimpan. Data manual juga tidak boleh dikosongkan oleh re-import.
- Snapshot lama hasil skema awal diberi penanda belum terverifikasi sampai sumber asli ditinjau atau diimpor kembali; angka master lama bukan hasil deduplikasi final.

### 9.5 Hasil pembacaan sumber awal dan backup

| Bulan 2026 | Baris | Total saldo piutang |
|---|---:|---:|
| Januari | 125 | Rp2.084.275.782 |
| Februari | 123 | Rp2.008.111.075 |
| Maret | 121 | Rp2.156.589.023 |
| April | 117 | Rp1.994.180.172 |
| Mei | 114 | Rp1.865.969.776 |
| Juni | 112 | Rp1.739.922.186 |
| Juli | 108 | Rp1.635.027.018 |
| Agustus | 105 | Rp1.517.721.823 |

Jumlah snapshot sumber pada tabel di atas dapat dipakai untuk rekonsiliasi. Jumlah Mitra unik tidak dicantumkan sebagai angka target karena hanya boleh dihasilkan setelah pencocokan multi-atribut dan seluruh kasus ambigu untuk cakupan data tersebut selesai divalidasi.

Backup sebelum penerapan aturan final dan import ulang tersimpan di `storage/backups/before-pumk-bri-final-20260917-141605.sql` dengan SHA-256 `234F185057354FFD430B58B67BA44629C29C1A2DC68D6BF0ABCF655F974DC96F`. Backup identitas sebelumnya tetap dipertahankan sebagai titik pemulihan tambahan.

Hasil import ulang final pada 17 September 2026 adalah sebagai berikut:

| Pemeriksaan | Hasil |
|---|---:|
| Snapshot Januari-Agustus terverifikasi | 925 |
| Snapshot legacy/belum terverifikasi | 0 |
| Baris review identitas tertunda | 0 |
| Keputusan review eksplisit yang diterapkan | 26 baris dalam 7 grup |
| Snapshot tanpa fasilitas | 0 |
| Duplikasi `(fasilitas_id, bulan, tahun)` | 0 |
| Mitra berbeda yang muncul pada 2026 | 132 (hasil data, bukan target deduplikasi) |
| Fasilitas aktif / lunas | 103 / 29 |

Tujuh grup yang telah divalidasi dihubungkan eksplisit ke fasilitas lama: `randi galang saputr*` ke fasilitas 3, `bibi*` ke 14, `agus siswoy*` ke 83, `apriliyant*` ke 92, `djarwiat*` ke 96, `eka rusmin*` ke 111, dan `ida ayu krisnawat*` ke 115. Khusus fasilitas 14, snapshot Januari-April tetap ada, Mei-Juli memang tidak ada pada sumber, dan Agustus kembali dengan saldo nol. Statusnya `lunas`, tanpa membuat tanggal pelunasan fiktif; `first_seen_period=2026-01-01` dan `last_seen_period=2026-08-01`.

### 9.6 Pemetaan ke dashboard PUMK BRI

Dashboard publik PUMK BRI menggunakan pilihan tahun gabungan dari `pumk_bri_snapshot_bulanan`, `pumk_bri_rka_tahunan`, dan `pumk_bri_penyaluran_bulanan`. Tahun yang baru memiliki input RKA/realisasi tetap dapat dipilih meskipun snapshot bulanannya belum diimpor. Semua angka diturunkan dari sumber yang benar-benar tersedia, bukan dari target jumlah mitra yang ditentukan di awal.

| Komponen dashboard | Sumber data |
|---|---|
| RKA Penyaluran | `pumk_bri_rka_tahunan.nominal_rka` untuk tahun terpilih; satu record per tahun dan dapat diedit Admin PUMK |
| Realisasi Penyaluran | Jumlah `pumk_bri_penyaluran_bulanan.nominal_penyaluran` untuk tahun terpilih; sama sekali tidak memakai tanggal pencairan workbook |
| Jumlah Outstanding | Total `saldo_piutang` pada snapshot bulan terakhir tahun terpilih |
| Total Mitra Binaan | Jumlah `mitra_id` berbeda yang memiliki minimal satu snapshot pada tahun terpilih, bukan hanya bulan terakhir. Ditandai sementara bila snapshot legacy atau review identitas masih ada. |
| Progres Penyaluran | Realisasi dibagi RKA. Tanpa RKA ditampilkan `Belum tersedia`; bila RKA ada tetapi belum ada input bulanan, realisasi Rp0 dan progres 0% |
| Sektor Ekonomi | Outstanding snapshot terakhir dikelompokkan berdasarkan `sektor_usaha_sumber` pada periode tersebut |
| Kualitas Piutang | Outstanding dan jumlah fasilitas/mitra snapshot terakhir dikelompokkan berdasarkan kode L/KL/D/M |
| Sebaran wilayah dan Data Mitra | Snapshot terakhir dikelompokkan berdasarkan `wilayah_sumber` pada periode tersebut. Mitra unik dihitung per wilayah; bila satu Mitra memiliki fasilitas pada dua wilayah, tabel memberi catatan bahwa angka antarwilayah tidak aditif. |
| Grafik bulanan | Total outstanding setiap bulan pada tahun terpilih |
| Panel bulanan | Menampilkan 12 input realisasi Admin PUMK; nilai nol berbeda dari bulan yang belum diinput |

Koordinat geografis tidak boleh dibuat-buat dari nama wilayah. Jika sumber belum memuat koordinat yang sah, dashboard menampilkan sebaran sebagai tabel/grafik wilayah, bukan titik peta sintetis. Nilai RKA tidak diturunkan dari outstanding karena keduanya memiliki makna bisnis berbeda. Jika suatu tahun hanya memiliki input RKA/realisasi dan belum ada snapshot, dashboard menampilkan `Belum tersedia` untuk outstanding dan jumlah Mitra, bukan angka nol seolah-olah merupakan hasil bisnis.

Dashboard juga menampilkan peringatan validasi saat `snapshot_belum_terverifikasi` atau `identitas_menunggu_review` masih lebih dari nol. Peringatan ini tidak mengubah data bisnis; tujuannya agar angka hasil relasi legacy tidak disalahartikan sebagai deduplikasi final.

## 10. Koreksi notasi ilmiah PUMK Internal (20 September 2026)

Koreksi ini **hanya** menyentuh `pumk_pinjaman.pinjaman_pokok`, total kontrak turunan, `pumk_saldo_awal.pokok_masuk`, dan total pokok masuk pada `baseline_sumber`. Tabel PUMK BRI tidak disentuh. Reader XLSX mengembalikan nilai numeric mentah seperti `1.5E7`; parser impor lama membuang huruf `E` sebelum konversi sehingga nilainya menjadi `1.57`. Parser sekarang mengembangkan eksponen sebelum pemformatan desimal.

Sumber yang direkonsiliasi: workbook V2 dengan SHA-256 `14c466ca76ce62596052e3614fc6b7b4176c31b07c1407e9adb1b29cc97220b4`, batch impor 8, 394 baris. Dry-run koreksi menemukan 278 pokok kontrak dan 77 pokok saldo awal yang cocok persis dengan hasil parser lama; tidak ada konflik. Sebelum perubahan, backup lengkap dibuat pada `storage/backups/before-pumk-scientific-repair-20260920-131335.sql` (1.715.655 byte, SHA-256 `EF1019DD90DC4CD0D9C497B4A102215649496D5A356E6A7FDA0A1830B21C60DA`). Koreksi kemudian diterapkan dalam transaksi tanpa re-import penuh, menghapus angsuran, atau mengganti path dan status manual.

| Pemeriksaan | Sebelum | Sesudah |
|---|---:|---:|
| Baris sumber diperiksa | 394 | 394 |
| Baris dengan selisih pada kolom keuangan AD/AF/AG/AU/AV/AW/AX/AY/AZ | 278 | 2 |
| AD pokok kontrak berbeda | 278 | 0 |
| AX pokok saldo awal berbeda | 77 | 0 |
| AF/AU/AV/AY/AZ berbeda | 0 | 0 |
| Angsuran manual terjaga | 1 | 1 |

Nomor sumber 252 sekarang memiliki pokok Rp15.000.000, total kontrak Rp16.274.014, sisa pokok Rp13.333.541, dan total sisa Rp14.287.710. Nomor 332 memiliki pokok Rp12.000.000, total kontrak Rp13.019.211, sisa pokok Rp4.002.007, dan total sisa Rp4.119.210. Kartu dari service dimulai dengan pokok kontrak tersebut. Angsuran manual setelah cutoff pada nomor 323 tetap mengurangi sisa cache tanpa mengubah baseline sumber.

Dua selisih satu sen yang tersisa berasal dari rumus/total pada workbook: nomor 197 `AG` Rp64.867.725,90 sedangkan `AD + AF` Rp64.867.725,89; nomor 198 `AW` Rp0,22 sedangkan `AU + AV` Rp0,23. Nilai komponen sumber dan baseline tidak dipaksa berubah untuk mengejar total yang tidak konsisten. Nomor 205 memiliki `AD` kosong dan tetap tidak diberi pokok rekaan. Ketiga nomor tersebut memerlukan keputusan kualitas data dari pemilik sumber sebelum koreksi bisnis lanjutan.

Perbaikan ulang aman melalui `php artisan pumk:repair-scientific-money FILE --expected-hash=SHA256` untuk dry-run. Opsi `--apply --backup=PATH_SQL` hanya menerima dump di `storage/backups` dan nilai DB yang persis cocok dengan jejak parser lama; pelaksanaan kedua idempoten (nol koreksi). Tes regresi mencakup parser, baseline, angsuran manual, status lunas, kartu Admin/Super Admin, serta respons Excel/PDF.

## 11. Filter tahun, histori lama, dan dokumen kontrak PUMK Internal

Fitur ini hanya berlaku untuk Kartu Piutang PUMK Internal (`pumk_mitra`, `pumk_pinjaman`, `pumk_angsuran`), bukan dashboard atau snapshot PUMK BRI. Pilihan tahun berasal dari rentang tenor, angsuran aktual termasuk di luar tenor, saldo awal, dan penyesuaian sumber. Halaman Admin dan pemantauan Super Admin membuka tahun terbaru; pilihan **Semua Tahun** tersedia. Service menghitung saldo pada timeline penuh lebih dulu, baru membatasi baris yang terlihat. Excel/PDF mengikuti tahun yang dipilih dan mencantumkannya pada nama file serta isi kartu.

Revisi final tidak memakai status kelengkapan histori per tahun. Tabel `pumk_histori_tahun` yang sempat ditambahkan oleh migration `000003` dihapus kembali oleh migration `000004`. Untuk setiap tahun yang dipilih, bulan dalam timeline kartu tetap ditampilkan; bulan tanpa transaksi memakai tanda `-` di UI/Excel/PDF dan hanya dibentuk di memori oleh `KartuPiutangService`, sehingga tidak menciptakan transaksi nol di database. Admin melengkapi pembayaran lama melalui form angsuran yang sama seperti pembayaran lain. Benturan dengan `pumk_saldo_awal` tetap memerlukan peringatan, konfirmasi penghapusan saldo awal, rekonsiliasi baseline, dan perhitungan ulang untuk mencegah hitung ganda.

Tabel `pumk_pinjaman_dokumen` menyimpan satu dokumen privat per pinjaman/jenis (`spj_awal`, `reschedule_1` sampai `reschedule_4`). Nomor kontrak tetap berada di `pumk_pinjaman`; dokumen hanya bisa diunggah jika nomor jenis kontrak itu ada. Admin PUMK dapat mengunggah, melihat, mengunduh, mengganti, dan menghapus; Super Admin hanya melihat/mengunduh melalui route terotorisasi. Berkas disimpan pada disk `local`, format PDF/JPG/JPEG/PNG, batas default 10 MB (`PUMK_CONTRACT_DOCUMENT_MAX_KB`). Re-import tidak mengubah tabel atau file dokumen manual; perubahan nomor kontrak yang sudah memiliki dokumen ditandai pada warning baris impor untuk review.

Sebelum migrasi `2026_09_20_000003_add_pumk_history_coverage_and_loan_documents`, backup MySQL dibuat di `storage/backups/before-pumk-year-documents-20260920-200859.sql` (1.719.000 byte; SHA-256 `04524C07CDCC9070C488DF5250180496380FFE40E114598F7D71A8E6DE515BEC`). Migrasi berhasil dijalankan pada 20 September 2026. Tidak ada impor ulang atau perubahan tabel PUMK BRI dalam pekerjaan ini.

Sebelum revision migration `2026_09_20_000004_remove_pumk_history_completion_status`, tabel status terverifikasi kosong. Backup terbaru dibuat di `storage/backups/before-pumk-year-filter-table-revision-20260920-211711.sql` (1.725.129 byte; SHA-256 `7C55EC347A6C5917A07459085FA6500D4958C5C29EF327F122EAF169E935B236`). Migration berhasil dijalankan; `pumk_histori_tahun` sudah tidak ada, sedangkan `pumk_pinjaman_dokumen` tetap tersedia.

## 12. Bukti pembayaran per angsuran PUMK Internal

Migration `2026_09_20_000005_add_payment_proof_to_pumk_angsuran` menambahkan path privat, nama file asli, MIME, ukuran, dan waktu unggah pada `pumk_angsuran`. Satu angsuran memiliki maksimal satu bukti aktif; nomor bukti tetap kolom terpisah. Fitur ini tidak menyentuh tabel PUMK BRI.

Admin PUMK dapat mengunggah PDF/JPG/JPEG/PNG (default maksimal 10 MB), melihat, mengunduh, mengganti, dan menghapus bukti untuk angsuran manual (`batch_id` kosong, `created_by` terisi). Super Admin hanya dapat melihat/mengunduh. Berkas berada pada disk privat `local` dan diakses melalui route terotorisasi, bukan URL storage publik. Penggantian baru menghapus file lama setelah transaksi DB berhasil; penghapusan bukti tidak menghapus angsuran. Semua upload/ganti/hapus tercatat di `pumk_activity_logs` tanpa isi file atau informasi sensitif. Angsuran hasil impor tetap read-only; re-import tidak mengubah bukti milik angsuran manual. Status lunas mempertahankan bukti sebagai histori. Export Excel/PDF tetap berisi nomor bukti, bukan file atau URL privat.

Pada angsuran historis yang tumpang tindih dengan Saldo Awal, form meminta konfirmasi sebelum upload dikirim agar file tidak hilang saat Saldo Awal dihapus dan baseline dihitung ulang. Jika JavaScript tidak aktif, halaman konfirmasi meminta pengguna memilih ulang file yang sebelumnya dipilih. Filter tahun hanya menampilkan tautan bukti dari angsuran yang tampil pada tahun tersebut.

Sebelum migration, backup lengkap dibuat di `storage/backups/before-pumk-payment-proof-20260920-220811.sql` (1.723.733 byte; SHA-256 `32A4B5A645ECB7E85733F316098FD0141E958B548E0BC68294EC15336B826C77`). Migration sudah dijalankan sebagai batch 37. Pengujian fitur mencakup file valid/tidak valid/terlalu besar, CRUD bukti, akses Super Admin read-only, re-import, pinjaman lunas, filter tahun, dan konfirmasi histori.
