# Rencana dan Checklist UAT TJSLINKA

Dokumen ini adalah panduan User Acceptance Test (UAT) sebelum TJSLINKA dipresentasikan dan diserahkan kepada Tim IT. UAT dibagi menjadi pemeriksaan otomatis dan pemeriksaan manual. Tes otomatis membuktikan aturan sistem, validasi, perubahan status, relasi database, enkripsi, dan hak akses. Tes manual membuktikan tampilan, kemudahan penggunaan, kualitas berkas, browser, jaringan, serta kesiapan operasional.

Status baseline 23 September 2026 pada commit `6b807a6` dan PHP 8.3.30:

| Pemeriksaan | Hasil |
|---|---:|
| Paket UAT terfokus | 173 tes, 1.976 assertion, seluruhnya lulus |
| Regression suite penuh | 190 tes, 2.125 assertion, seluruhnya lulus |
| UAT manual | Belum dijalankan; gunakan checklist di bawah |

Kelulusan tes otomatis bukan sign-off UAT akhir. Skenario manual, infrastruktur server, dan persetujuan pemilik proses tetap wajib.

## 1. Target kelulusan

UAT dinyatakan lulus apabila:

1. seluruh tes otomatis lulus;
2. tidak ada defect Severity 1 atau Severity 2 yang terbuka;
3. semua skenario wajib manual berstatus `PASS`;
4. data pengujian tidak bercampur dengan data produksi;
5. backup dan simulasi pemulihan database berhasil;
6. Admin TJSL, Super Admin, Admin PUMK, dan perwakilan pengguna menyetujui hasil UAT.

Klasifikasi defect:

| Severity | Arti | Contoh | Keputusan |
|---|---|---|---|
| S1 - Kritis | Sistem atau data tidak dapat dipakai/aman | login semua peran gagal, data hilang, PII bocor | UAT berhenti |
| S2 - Tinggi | Proses bisnis utama gagal tanpa solusi aman | approval salah status, angsuran salah hitung | wajib diperbaiki sebelum rilis |
| S3 - Sedang | Fungsi pendukung bermasalah tetapi ada jalan lain | filter tertentu salah, ekspor kurang rapi | boleh lanjut setelah ada rencana perbaikan |
| S4 - Rendah | Kosmetik atau teks | jarak, warna, salah ketik | dicatat untuk penyempurnaan |

## 2. Persiapan sebelum UAT

Gunakan lingkungan UAT/staging, bukan database produksi.

1. Catat commit Git yang diuji dengan `git rev-parse --short HEAD`.
2. Buat database khusus, misalnya `tjslinka_uat`, dan storage khusus UAT.
3. Salin `.env` menjadi konfigurasi UAT. Pastikan `APP_ENV=staging`, `APP_DEBUG=false`, URL benar, dan kredensial bukan kredensial produksi.
4. Jalankan migration dan seeder referensi yang diperlukan.
5. Sediakan akun terpisah:
   - pengguna umum UAT;
   - Admin TJSL UAT;
   - Super Admin UAT;
   - Admin PUMK UAT.
6. Gunakan nama data berawalan `UAT-YYYYMMDD-`, misalnya `UAT-20260923-PROGRAM-PKS-01`.
7. Jangan gunakan KTP, rekening, telepon, atau dokumen pribadi asli. Gunakan data sintetis.
8. Siapkan file uji:
   - PDF/DOCX/XLSX valid berukuran kecil;
   - file dengan ekstensi tidak diizinkan;
   - dokumen Program/Bantuan di atas 10 MB;
   - foto di atas 20 MB dan file non-gambar;
   - dokumen SPJ PUMK mendekati 150 MB dan di atas 150 MB;
   - bukti pembayaran PUMK di atas 10 MB.
9. Uji minimal pada Chrome dan Edge terbaru, resolusi desktop 1920x1080 dan mobile sekitar 390x844.
10. Buka DevTools pada beberapa skenario utama dan pastikan tidak ada error JavaScript atau request 500.

Format bukti per skenario:

| Kolom | Isi |
|---|---|
| ID UAT | Contoh `UAT-C-07` |
| Tester / tanggal | Nama dan waktu pengujian |
| Data uji | Nama program/mitra yang digunakan |
| Hasil aktual | Ringkas dan objektif |
| Status | `PASS`, `FAIL`, atau `BLOCKED` |
| Bukti | Screenshot/video, response, atau log tanpa PII |
| Defect | ID defect bila gagal |

## 3. Menjalankan tes otomatis

Paket terfokus menjalankan tes yang berhubungan langsung dengan ruang lingkup UAT ini. Database otomatis menggunakan SQLite `:memory:` sehingga database aplikasi tidak diubah.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-uat.ps1
```

Untuk regression test seluruh proyek:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-uat.ps1 -Full
```

Simpan keluaran terminal sebagai bukti UAT. Bila satu tes gagal, jangan menyatakan UAT lulus hanya karena pengujian manual terlihat benar.

## A. Autentikasi dan Struktur Dasar

Urutan ini harus dijalankan lebih dahulu karena semua modul bergantung pada sesi dan peran.

| ID | Skenario dan langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-A-01 | Buka `/`, `/teras-tjsl`, dan `/program-tjsl/overview` tanpa login. | Dialihkan ke login pengguna umum; isi halaman privat tidak terlihat. | `PublicAuthenticationTest`, `UatFoundationTest` |
| UAT-A-02 | Login pengguna umum dengan kredensial benar, buka Home, Teras, Program, Profil, lalu logout. | Login berhasil, seluruh menu dapat diakses, logout menghapus sesi, halaman kembali meminta login. | `PublicAuthenticationTest` |
| UAT-A-03 | Ulangi login dengan password salah. | Pesan gagal tidak membocorkan apakah username terdaftar. | `PublicAuthenticationTest` |
| UAT-A-04 | Lakukan enam percobaan login salah pada login publik, Admin TJSL, Admin PUMK, dan Super Admin. | Percobaan keenam dibatasi dan menampilkan pesan terlalu banyak percobaan. | `LoginSecurityTest` |
| UAT-A-05 | Login Admin TJSL pada `/admin/login`. | Masuk ke dashboard Admin TJSL dan tidak memperoleh sesi Super Admin/PUMK. | `AdminAuthenticationTest`, `PumkAdminAuthenticationTest` |
| UAT-A-06 | Login Super Admin pada `/superadmin/login`. | Masuk ke dashboard Super Admin; menu review tersedia. | `AdminAuthenticationTest` |
| UAT-A-07 | Login Admin PUMK pada `/admin-pumk/login`. | Masuk ke dashboard Admin PUMK; menu Kartu Piutang tersedia. | `PumkAdminAuthenticationTest` |
| UAT-A-08 | Dengan satu peran, ketik URL milik peran lain. | Ditolak/diarahkan ke login yang tepat; tidak ada data lintas peran. | `PumkAdminAuthenticationTest`, `SuperAdminPhaseThreeTest`, `UatFoundationTest` |
| UAT-A-09 | Login memakai akun `is_active=false`. | Login ditolak. | tes autentikasi masing-masing peran |
| UAT-A-10 | Login dengan akun `must_change_password=true`, lalu coba buka dashboard. | Dialihkan ke Profil/ganti password; dashboard terkunci sampai password baru valid disimpan. | `LoginSecurityTest`, `AdminAuthenticationTest` |
| UAT-A-11 | Ganti profil dan password melalui menu profil setiap peran. Uji password lama salah, konfirmasi berbeda, dan password valid. | Validasi muncul untuk input salah; password valid tersimpan; login berikutnya memakai password baru. | tes autentikasi/profil |
| UAT-A-12 | Buka tampilan desktop dan mobile. Periksa logo, sidebar, profil, menu aktif, tombol keluar, modal, dan navigasi keyboard. | Tidak ada elemen tertutup/terpotong; fokus keyboard terlihat; profil dan logout bisa dipakai. | Manual |

Catatan: bukti screenshot login tidak boleh memperlihatkan password.

## B. Home, Teras TJSL, dan Program TJSL

| ID | Skenario dan langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-B-01 | Setelah login publik, buka Home. Cocokkan kartu/ringkasan dengan data sumber UAT. | Angka, label, pilar, dan tautan navigasi tampil tanpa error. | `PublicHomeDashboardTest`, `DashboardReferenceDataTest` |
| UAT-B-02 | Buka Teras TJSL dengan data produk dan paket aktif. | Hanya data yang layak tampil yang muncul, gambar tidak pecah, susunan responsif. | `TerasTjslManagementTest` |
| UAT-B-03 | Admin membuat, mengubah, menonaktifkan, dan mengaktifkan produk Teras. | Perubahan tersimpan dan status aktif menentukan tampilan publik. | `TerasTjslManagementTest` |
| UAT-B-04 | Admin membuat, mengubah, menonaktifkan, dan mengaktifkan paket Teras. | Perubahan tersimpan dan tampilan publik mengikuti status. | `TerasTjslManagementTest` |
| UAT-B-05 | Buka Overview Program TJSL tanpa kata kunci, lalu gunakan pencarian dan filter tahun. | Hasil sesuai kata kunci/tahun; query tetap konsisten ketika berpindah halaman. | `ProgramArchiveMonitoringTest`, `BantuanCsrOverviewIntegrationTest` |
| UAT-B-06 | Biarkan Overview terbuka ketika data monitoring berubah. | Komponen monitoring memperbarui data tanpa menggandakan kartu atau merusak filter. | `BantuanCsrOverviewIntegrationTest` |
| UAT-B-07 | Buka Rincian dan filter berdasarkan pilar; pindah pagination. | Hanya program pada pilar yang benar tampil dan filter bertahan. | `ProgramRincianTujuanTest` |
| UAT-B-08 | Buka detail program selesai. Klik setiap dokumen `Lihat` dan `Unduh`. | Dokumen milik program yang benar tampil/terunduh; nama dan tipe file benar. | `ProgramRincianTujuanTest` |
| UAT-B-09 | Coba mengakses dokumen menggunakan pasangan program-document yang tidak cocok. | Request ditolak/not found; dokumen program lain tidak bocor. | `ProgramRincianTujuanTest` |
| UAT-B-10 | Periksa video monitoring pada Overview dan Realisasi Program. Uji play/pause, mute, loop, rasio, serta beban awal. | Video terbaru tampil pada kedua lokasi, tidak menyebabkan layout bergeser atau error 404. | Manual |
| UAT-B-11 | Uji kartu realisasi dengan badge PKS, NON-PKS, dan Bantuan TJSL serta warna keempat pilar. | Badge benar berdasarkan jenis data dan batas/warna pilar terlihat jelas. | Manual + tes overview |

## C. Program TJSL Internal - Admin

Jalankan alur PKS dan NON-PKS dengan nama berbeda. Foto dokumentasi hanya diunggah bersama BAST pada tahap 2.

| ID | Skenario dan langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-C-01 | Admin memilih `Program TJSL` lalu jenis PKS. | Form PKS terbuka dan badge/jenis tersimpan sebagai PKS. | `ProgramCooperationTypeTest`, `AdminPhaseTwoTest` |
| UAT-C-02 | Isi identitas program tetapi kosongkan field wajib, tujuan, atau dokumen awal lalu kirim. | Validasi tampil di field yang benar; tidak ada pengajuan parsial yang salah status. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-03 | Simpan PKS sebagai draft dengan data valid. | Status `draft`; masih dapat diedit; belum masuk antrean Super Admin dan belum tampil di Overview. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-04 | Lengkapi PKS dan unggah lima dokumen awal: Proposal, Survei, Kajian Kelayakan, Kajian Risiko, dan PKS. Klik ajukan. | Status `pending_fase1`, waktu submit tercatat, dokumen tersimpan privat, form tahap 2 belum dapat dipakai. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-05 | Unggah ekstensi terlarang atau dokumen awal di atas 10 MB. | Ditolak dengan pesan validasi; file tidak disimpan. | tes workflow/validasi + Manual ukuran nyata |
| UAT-C-06 | Super Admin membuka pengajuan PKS dan memilih tolak dengan alasan. | Status `rejected_fase1`, alasan dan reviewer tercatat, Admin dapat melihat alasan, data tidak tampil sebagai program aktif. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-07 | Pada data PKS lain, Super Admin menyetujui tahap 1. | Status `approved_fase1`; program mulai layak tampil di Overview; Admin memperoleh akses tahap 2. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-08 | Admin membuka tahap 2 dan mengunggah BAST tanpa foto, lalu dengan foto valid dan keterangan. | BAST wajib; foto opsional maksimal 10 gambar dan 20 MB/gambar; status menjadi `pending_fase2`; urutan/keterangan foto tersimpan. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-09 | Unggah PDF sebagai foto atau foto di atas 20 MB. | Validasi menolak; BAST/foto invalid tidak mengubah status. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-10 | Super Admin menolak BAST dengan alasan. | Program kembali ke `approved_fase1`, alasan BAST terlihat, Admin hanya memperbaiki BAST/dokumentasi. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-11 | Admin mengganti BAST/foto lalu mengajukan ulang; Super Admin menyetujui. | Status `completed`; reviewer dan waktu tahap 2 tercatat; hasil tampil pada realisasi publik. | `ProgramTwoPhaseWorkflowTest` |
| UAT-C-12 | Buat NON-PKS. Verifikasi hanya Proposal dan Survei yang wajib, lalu selesaikan dua tahap. | Jenis tersimpan NON-PKS; validasi tidak meminta dokumen PKS yang tidak berlaku; badge publik benar. | `ProgramCooperationTypeTest` |
| UAT-C-13 | Coba Admin lain membuka/mengubah data yang bukan miliknya bila pembatasan kepemilikan diterapkan. | Akses mengikuti kebijakan aplikasi dan tidak memungkinkan perubahan tidak sah. | `SubmissionWorkflowFixTest` + Manual |
| UAT-C-14 | Periksa notifikasi Admin/Super Admin pada submit, approve, reject, resubmit, dan completed. | Notifikasi muncul sekali, mengarah ke data yang benar, dan jumlah unread akurat. | tes workflow + Manual |

Rantai status yang wajib dibuktikan:

```text
draft -> pending_fase1 -> approved_fase1 -> pending_fase2 -> completed
                    \-> rejected_fase1
pending_fase2 -> (BAST ditolak) approved_fase1 -> pending_fase2
```

## E. Bantuan TJSL / Bantuan CSR

Nama menu antarmuka menggunakan Bantuan TJSL; nama model/route lama masih memakai `BantuanCsr` untuk kompatibilitas kode.

| ID | Skenario dan langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-E-01 | Admin membuka Buat Bantuan TJSL. Isi nama, deskripsi, pilar, anggaran, target, dan rincian bantuan. | Baris target/rincian dapat ditambah dan data tersimpan sesuai urutan. | `BantuanCsrTwoPhaseWorkflowTest`, `BantuanCsrOverviewIntegrationTest` |
| UAT-E-02 | Kirim tanpa Proposal (A), Survei (B), target, atau rincian wajib. | Validasi muncul; status tidak berubah menjadi pengajuan. | `BantuanCsrTwoPhaseWorkflowTest` |
| UAT-E-03 | Simpan draft lalu edit kembali. | Status `draft`, data dapat diedit, belum masuk antrean review. | tes workflow Bantuan |
| UAT-E-04 | Ajukan data lengkap dengan Proposal dan Survei. | Status `pending_fase1`; dokumen tersimpan privat; Super Admin dapat melakukan review. | `BantuanCsrTwoPhaseWorkflowTest` |
| UAT-E-05 | Super Admin menolak tahap 1 dengan alasan. | Status `rejected_fase1`; alasan terlihat oleh Admin; penolakan tidak dianggap selesai. | `BantuanCsrTwoPhaseWorkflowTest` |
| UAT-E-06 | Super Admin menyetujui tahap 1. | Status `approved_fase1`; Bantuan layak tampil di Overview; form BAST aktif. | `BantuanCsrTwoPhaseWorkflowTest`, `BantuanCsrOverviewIntegrationTest` |
| UAT-E-07 | Admin unggah BAST dan foto dokumentasi beserta keterangan. | BAST tersimpan satu dokumen, foto tersimpan berurutan, status `pending_fase2`. | `BantuanCsrTwoPhaseWorkflowTest` |
| UAT-E-08 | Unggah file non-gambar/lebih dari 20 MB sebagai foto atau BAST lebih dari 10 MB. | Ditolak tanpa menyisakan file/status setengah jadi. | `BantuanCsrTwoPhaseWorkflowTest` + Manual ukuran nyata |
| UAT-E-09 | Super Admin menolak BAST, Admin mengganti BAST/foto, lalu ajukan ulang. | Alasan terlihat; status kembali `approved_fase1` saat ditolak dan `pending_fase2` setelah diajukan ulang. | `BantuanCsrTwoPhaseWorkflowTest` |
| UAT-E-10 | Super Admin menyetujui BAST. | Status `completed`; Bantuan tampil di realisasi dan detail publik. | `BantuanCsrTwoPhaseWorkflowTest`, `ProgramRincianTujuanTest` |
| UAT-E-11 | Pada detail publik, klik Lihat/Unduh dokumen dan uji pasangan bantuan-document yang salah. | Berkas benar bisa dilihat/diunduh; pasangan yang salah ditolak. | `ProgramRincianTujuanTest` |
| UAT-E-12 | Coba Super Admin mengubah isi, mengunggah file, atau menghapus Bantuan. | Tidak tersedia route/form mutasi; Super Admin hanya review approve/reject. | `BantuanCsrTwoPhaseWorkflowTest` |

## F. Filter Status Program dan Bantuan - Admin dan Super Admin

Siapkan minimal satu data untuk tiap keadaan: draft, menunggu tahap 1, ACC tahap 1, BAST ditolak, menunggu tahap 2, ditolak tahap 1, dan selesai.

| ID | Langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-F-01 | Admin memilih `Semua Status Aktif`. | Draft/aktif yang relevan tampil; penolakan final tidak tercampur secara default. | `TjslStatusFilterTest` |
| UAT-F-02 | Admin memilih `Menunggu Pengecekan`. | `pending_fase1` dan `pending_fase2` tampil; ACC/selesai tidak tampil. | `TjslStatusFilterTest` |
| UAT-F-03 | Admin memilih `ACC Tahap 1`. | `approved_fase1` biasa tampil; data BAST ditolak tidak salah dilabeli ACC biasa. | `TjslStatusFilterTest` |
| UAT-F-04 | Admin memilih `Ditolak`. | Penolakan tahap 1 dan BAST ditolak tampil dengan label/alasan yang dapat dibedakan. | `TjslStatusFilterTest` |
| UAT-F-05 | Admin memilih `Selesai`. | Hanya `completed` tampil. | `TjslStatusFilterTest` |
| UAT-F-06 | Ulangi F-01 sampai F-05 pada Program dan Bantuan. | Hasil konsisten pada kedua modul. | `TjslStatusFilterTest` |
| UAT-F-07 | Super Admin mencoba semua filter. | Draft Admin tidak tampil; pengajuan yang sudah disubmit muncul sesuai filter. | `TjslStatusFilterTest` |
| UAT-F-08 | Pilih pilar dan status bersamaan, lalu navigasi pagination/detail/kembali. | Query pilar dan status dipertahankan; hasil tidak melebar. | `TjslStatusFilterTest`, `BantuanCsrPillarGroupingTest` |
| UAT-F-09 | Masukkan status query tidak valid, termasuk `draft` di daftar Super Admin. | Request divalidasi/ditolak; tidak menyebabkan error 500 atau bypass. | `TjslStatusFilterTest` |
| UAT-F-10 | Periksa empty state setiap filter yang tidak punya data. | Pesan kosong jelas; bukan halaman putih atau spinner terus-menerus. | Manual |

## G. Dashboard PUMK - Kartu Piutang

Bagian ini khusus PUMK Internal/Kartu Piutang PT INKA, bukan PUMK BRI.

| ID | Skenario dan langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-G-01 | Login Admin PUMK dan buka Home. Cocokkan total binaan, aktif/lunas, dan kolektibilitas dengan daftar mitra. | Ringkasan konsisten dengan database dan tidak menampilkan catatan yang sudah dihapus. | `PumkMonitoringDashboardTest`, `PumkLoanCompletionTest` |
| UAT-G-02 | Buka daftar mitra; uji pencarian, sektor, wilayah, status, kolektibilitas, dan filter tahun. | Tabel tetap tampil sesuai desain; hasil dan jumlah sesuai filter. | `PumkMitraManagementTest`, `PumkYearAndContractDocumentTest` |
| UAT-G-03 | Tambah mitra/pinjaman menggunakan data sintetis. | Mitra dan pinjaman tersimpan satu kali; relasi sektor/wilayah benar; field wajib tervalidasi. | `PumkMitraManagementTest` |
| UAT-G-04 | Edit data dan biarkan field opsional kosong. | Field yang boleh kosong tidak memicu error; perubahan yang dimaksud tersimpan. | `PumkMitraManagementTest` |
| UAT-G-05 | Unggah SPJ awal/reschedule PDF/JPG/PNG valid, buka `Lihat`, unduh, ganti, dan hapus sesuai kewenangan. | Berkas privat, file lama diganti/dihapus aman, nama/tanggapan browser benar. | `PumkYearAndContractDocumentTest` |
| UAT-G-06 | Uji dokumen SPJ 149 MB dan 151 MB. | Sekitar 149 MB diterima bila batas server mendukung; di atas 150 MB ditolak dengan pesan jelas. | `UatFoundationTest` + Manual end-to-end server |
| UAT-G-07 | Tambah angsuran dengan periode, pokok, bunga, denda, nomor bukti, dan file bukti valid. | Satu transaksi tersimpan; total/saldo/kolektibilitas dihitung ulang; bukti dapat dilihat/diunduh. | `PumkMitraManagementTest`, `PumkPaymentProofTest`, `KartuPiutangServiceTest` |
| UAT-G-08 | Masukkan periode yang sama dua kali untuk pinjaman yang sama. | Duplikasi ditolak; riwayat lama tidak tertimpa diam-diam. | `PumkMitraManagementTest` |
| UAT-G-09 | Edit angsuran dan hapus bukti pembayaran. | Nilai perhitungan diperbarui; hanya bukti yang dimaksud terhapus. | `PumkMitraManagementTest`, `PumkPaymentProofTest` |
| UAT-G-10 | Uji bukti pembayaran tipe terlarang atau lebih dari 10 MB. | Ditolak; angsuran/file tidak masuk dalam kondisi setengah jadi. | `PumkPaymentProofTest` |
| UAT-G-11 | Cocokkan saldo awal, angsuran sebelum/sesudah cutoff, saldo pokok/bunga/denda, tunggakan, dan kolektibilitas pada kartu. | Perhitungan mengikuti baseline sumber dan riwayat; tidak menghitung ganda. | `PumkPiutangCalculatorTest`, `KartuPiutangServiceTest`, `PumkImportServiceTest` |
| UAT-G-12 | Filter kartu berdasarkan tahun lama, tahun sekarang, dan tahun tanpa transaksi. | Saldo pembuka dan histori tetap bermakna; tabel tidak menghilangkan pinjaman secara salah. | `PumkYearAndContractDocumentTest` |
| UAT-G-13 | Ekspor Excel dan PDF untuk pinjaman aktif dan lunas. | File berhasil, nama/format benar, angka sama dengan layar, PII hanya tersedia bagi peran berwenang. | `PumkMitraManagementTest`, `PumkLoanCompletionTest` |
| UAT-G-14 | Tandai pinjaman lunas saat saldo belum memenuhi aturan, lalu pada data yang memenuhi aturan. | Kasus tidak valid ditolak; kasus valid mengubah status dan histori tetap tersedia. | `PumkLoanCompletionTest` |
| UAT-G-15 | Login Super Admin dan buka Monitoring Admin PUMK, daftar, kartu, bukti, dokumen, PDF, dan Excel. | Semua dapat dibaca/diunduh; tidak ada tombol/route tambah, edit, hapus, impor, angsuran, atau tandai lunas. | `SuperAdminPumkMonitoringTest`, `UatFoundationTest` |
| UAT-G-16 | Jalankan impor monitoring yang valid dan file salah format pada lingkungan UAT. | Data valid masuk sesuai aturan; file salah ditolak dengan ringkasan yang aman tanpa PII. | `MonitoringUploadAccessTest`, `MonitoringExcelImportTest`, `PumkImportServiceTest` |

Rekonsiliasi manual minimum untuk satu pinjaman:

1. catat nilai sumber pinjaman pokok dan bunga;
2. catat saldo awal/cutoff;
3. jumlahkan transaksi pokok, bunga, dan denda per periode;
4. cocokkan dengan layar kartu, PDF, dan Excel;
5. simpan lembar rekonsiliasi tanpa data PII asli sebagai bukti.

## H. Keamanan Data Sensitif

| ID | Skenario dan langkah | Hasil yang diharapkan | Bukti otomatis |
|---|---|---|---|
| UAT-H-01 | Periksa raw database mitra UAT untuk KTP, telepon, dan rekening. | Nilai terenkripsi tidak sama dengan plaintext; hash KTP tersedia untuk pencocokan bila diperlukan. | `PumkDatabaseSchemaTest` |
| UAT-H-02 | Periksa daftar, dashboard, URL, HTML source, log Laravel, dan pesan import. | Tidak ada KTP/rekening/telepon lengkap pada lokasi yang tidak berwenang. | Manual + tes schema/import |
| UAT-H-03 | Ambil URL dokumen privat kemudian logout dan buka ulang URL. | Akses ditolak/dialihkan ke login. | `UatFoundationTest`, tes dokumen |
| UAT-H-04 | Sebagai user/peran salah, buka URL dokumen PUMK, program, atau bantuan secara langsung. | Akses ditolak; file tidak bocor melalui URL tebakan. | `ProgramRincianTujuanTest`, `SuperAdminPumkMonitoringTest` |
| UAT-H-05 | Ganti ID dokumen pada URL agar tidak sesuai dengan parent program/mitra/pinjaman. | `404`/forbidden; dokumen milik record lain tidak dikirim. | `ProgramRincianTujuanTest`, tes dokumen PUMK |
| UAT-H-06 | Kirim form POST/PUT/DELETE tanpa CSRF melalui sesi browser. | Request ditolak oleh middleware CSRF. | Framework + Manual DevTools/Postman |
| UAT-H-07 | Uji upload dengan ekstensi ganda, MIME palsu, script, dan file di atas batas. | Validasi menolak dan file tidak dieksekusi/tersimpan di public. | tes validasi upload + Manual keamanan |
| UAT-H-08 | Periksa source repository dan konfigurasi deployment. | Tidak ada `.env`, password nyata, API key, dump database, atau file PII yang dipublikasikan ke Git. | Manual `git ls-files`/secret scan |
| UAT-H-09 | Pastikan akun awal wajib ganti password dan password lama tidak dapat dipakai setelah rotasi. | Rotasi dipaksa; password disimpan sebagai hash. | `LoginSecurityTest` |
| UAT-H-10 | Periksa cookie pada HTTPS staging. | Cookie session `HttpOnly`, `Secure`, dan kebijakan `SameSite` sesuai kebutuhan. | Manual browser |

## I. Infrastruktur dan Operasional

Bagian ini harus dilakukan bersama Tim IT karena beberapa hasil bergantung pada web server dan server database, bukan kode Laravel saja.

| ID | Pemeriksaan | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| UAT-I-01 | Versi runtime | Jalankan `php -v`, `composer check-platform-reqs`, `node -v`, dan `npm -v`. | PHP memenuhi `^8.3`; extension dan dependency lengkap. |
| UAT-I-02 | Instalasi/build bersih | Pada salinan baru, jalankan install Composer/NPM, migration, dan `npm run build`. | Tidak ada error; asset manifest tersedia. |
| UAT-I-03 | Konfigurasi produksi | Periksa `APP_ENV`, `APP_DEBUG`, `APP_URL`, timezone, mail, queue, cache, session, database, dan filesystem. | `APP_DEBUG=false`, URL/zone benar, rahasia dari environment, bukan hardcode. |
| UAT-I-04 | Batas upload 150 MB | Cocokkan Laravel 153600 KB dengan `upload_max_filesize`, `post_max_size`, Nginx `client_max_body_size`/Apache, proxy, dan timeout. | Seluruh lapisan lebih besar dari 150 MB plus overhead; file 149 MB berhasil. |
| UAT-I-05 | Storage | Pastikan `storage/app/private` persisten, writable oleh service account, tidak menjadi static public directory; storage publik memiliki link yang benar. | Upload, lihat, unduh, ganti, dan hapus bekerja setelah restart/deploy. |
| UAT-I-06 | Scheduler | Jalankan `php artisan schedule:list`; siapkan cron/Task Scheduler menjalankan `schedule:run` tiap menit. | `pumk:snapshot-bulanan` terdaftar tanggal 1 pukul 01:00 Asia/Jakarta dan tidak overlap. |
| UAT-I-07 | Queue | Bila deployment memakai queue asynchronous, jalankan worker/supervisor dan simulasi restart. | Job diproses, retry/failed jobs termonitor. Bila sync, keputusan didokumentasikan. |
| UAT-I-08 | Email/notifikasi | Gunakan mailbox UAT dan uji notifikasi penting. | Email/notifikasi terkirim satu kali; error tercatat tanpa rahasia. |
| UAT-I-09 | HTTPS dan proxy | Buka seluruh login melalui domain staging, inspeksi certificate dan mixed content. | HTTPS valid, redirect HTTP->HTTPS, asset tidak mixed-content. |
| UAT-I-10 | Backup | Buat backup database dan private storage sebelum UAT. | Backup selesai, checksum/ukuran tercatat, akses terbatas. |
| UAT-I-11 | Restore | Pulihkan backup ke instance kosong dan buka sampel program, bantuan, mitra, dokumen, serta kartu. | Relasi dan file pulih; waktu restore dicatat sebagai dasar RTO/RPO. |
| UAT-I-12 | Logging | Picu validasi normal dan satu error aman di staging; periksa log rotation dan permission. | Error dapat ditelusuri, tetapi log tidak mengandung password/PII lengkap. |
| UAT-I-13 | Kinerja | Uji halaman daftar, dashboard, pencarian, PDF/Excel, dan upload besar pada jaringan kantor. | Waktu respons disepakati Tim IT; tidak timeout atau memory exhausted. |
| UAT-I-14 | Multi-user | Dua tester melakukan submit/review pada data berbeda secara bersamaan. | Tidak ada status saling menimpa atau data tertukar. |
| UAT-I-15 | Health check | Buka `/up` dari monitoring internal. | Respons sehat saat aplikasi/database siap; akses dan alert disepakati IT. |
| UAT-I-16 | CI GitHub | Jalankan workflow pada versi PHP yang didukung dan telaah kegagalan/cancel. | Semua job target yang diwajibkan hijau; job dibatalkan tidak dianggap lulus. |
| UAT-I-17 | Rollback | Dokumentasikan versi rilis, backup, perintah rollback, dan PIC. Lakukan simulasi staging. | Aplikasi dapat kembali ke versi stabil tanpa kehilangan transaksi sah. |

Sebagian preflight I sudah dijaga oleh `UatFoundationTest`: isolasi database tes, route dan middleware kritis, storage privat, batas SPJ 150 MB, mode baca-saja Super Admin PUMK, dan scheduler snapshot bulanan.

## 4. Urutan eksekusi paling efisien

1. Jalankan paket otomatis. Perbaiki kegagalan fundamental sebelum pengujian manual.
2. Jalankan A untuk memastikan semua akun dan sesi sehat.
3. Siapkan data master/pilar dan jalankan B.
4. Buat satu PKS, satu NON-PKS, dan satu Bantuan melalui C dan E.
5. Gunakan data yang sama untuk menguji semua status pada F agar tidak membuat data berlebihan.
6. Gunakan satu mitra dengan dua pinjaman dan beberapa angsuran untuk G.
7. Lakukan pengujian negatif keamanan H setelah data utama tersedia.
8. Lakukan I bersama Tim IT pada lingkungan yang menyerupai produksi.
9. Jalankan kembali paket otomatis penuh setelah setiap bug diperbaiki.
10. Bekukan versi/commit, kumpulkan bukti, dan lakukan sign-off.

## 5. Matriks hasil akhir

| Bagian | Total skenario manual | Lulus | Gagal | Blocked | Penanggung jawab |
|---|---:|---:|---:|---:|---|
| A. Autentikasi | 12 |  |  |  |  |
| B. Home/Teras/Program | 11 |  |  |  |  |
| C. Program Internal | 14 |  |  |  |  |
| E. Bantuan TJSL | 12 |  |  |  |  |
| F. Filter Status | 10 |  |  |  |  |
| G. Kartu Piutang | 16 |  |  |  |  |
| H. Keamanan | 10 |  |  |  |  |
| I. Infrastruktur | 17 |  |  |  |  |
| **Total** | **102** |  |  |  |  |

## 6. Sign-off

| Peran | Nama | Keputusan | Tanggal | Catatan |
|---|---|---|---|---|
| Pemilik proses TJSL |  | Setuju / Tidak |  |  |
| Admin TJSL |  | Setuju / Tidak |  |  |
| Admin PUMK |  | Setuju / Tidak |  |  |
| Super Admin |  | Setuju / Tidak |  |  |
| Tim IT |  | Setuju / Tidak |  |  |

Versi yang ditandatangani harus menyebut commit Git, URL UAT, tanggal backup, hasil tes otomatis, daftar defect tersisa, dan keputusan go/no-go.

## 7. Tambahan UAT pelunasan dan monitoring PUMK internal (28 September 2026)

Ini khusus Kartu Piutang PUMK internal, bukan PUMK BRI. Alur data: kartu menghitung saldo sumber dan angsuran → Admin PUMK menandai lunas → metadata saldo asli, alasan, catatan, petugas, dan waktu penutupan tersimpan → pinjaman keluar dari piutang aktif sejak tanggal lokal penutupan → capture/snapshot periode terdampak perlu direkonsiliasi → grafik memakai nominal piutang aktif positif. Penutupan tidak menghapus saldo asli, menambah angsuran palsu, atau mencatat refund.

Toleransi runtime `PUMK_SETTLEMENT_TOLERANCE` default `0.00`. Rp10.000 pada fixture/UAT adalah **usulan pengujian, belum ketetapan resmi perusahaan**. Nilai toleransi yang berlaku saat tindakan tersimpan pada pinjaman, sehingga perubahan konfigurasi berikutnya tidak mengubah arti penutupan lama. Sebelum UAT pada database disposable/staging, Tim IT memastikan backup, target koneksi DB, dua migration `2026_09_28_000001` dan `2026_09_28_000002`, serta environment `PUMK_SETTLEMENT_TOLERANCE=10000.00`. Jangan menjalankan `migrate:fresh` atau `--reconcile` pada DB pengguna.

Definisi dashboard: KPI dan distribusi sektor/kualitas/provinsi adalah subtotal nominal pinjaman **terbuka, saldonya diketahui, dan totalnya positif** pada cutoff. Kelebihan bayar terbuka ditampilkan terpisah dengan tanda negatif. Total binaan adalah mitra unik pada piutang aktif positif, bukan semua mitra terdaftar. Bila ada saldo tidak diketahui, angka KPI merupakan subtotal. Kategori memakai riwayat per atribut bila ada, lalu snapshot sah, lalu nilai terkini yang dapat dibuktikan berlaku pada cutoff; data lama tanpa bukti historis tetap ditandai belum terverifikasi. Revisi snapshot menimpa detail sebelumnya; nomor revisi bukan arsip semua versi. Tahun PUMK INKA hanya 2025 sampai tahun berjalan Asia/Jakarta, tanpa mengubah rentang PUMK BRI.

Untuk setiap baris UAT berikut, isi commit, URL/environment, toleransi aktif, waktu, penguji, expected/actual, status PASS/FAIL/BLOCKED, bukti aman, dan nomor temuan. Status awal seluruh baris **BELUM DIUJI**.

| ID | Langkah pada fixture sintetis | Hasil yang diharapkan |
|---|---|---|
| U-P01 | Saldo pokok dan bunga 0; tandai lunas | Alasan normal; arsip dan histori/unduhan tetap ada. |
| U-P02 | Saldo +1.000 dan +10.000; isi alasan; tandai lunas | Toleransi inklusif, selisih asli, catatan, petugas, waktu, batas toleransi tersimpan. |
| U-P03 | Coba +10.000,01 atau catatan kosong | Ditolak tanpa perubahan status/audit. |
| U-P04 | Saldo -5.000; isi alasan dan konfirmasi pemeriksaan | Kelebihan bayar ditutup; tidak ada refund atau pembayaran fiktif. |
| U-P05 | Pokok +100.000 dan bunga -100.000 | Ditolak sebagai saldo komponen campuran, meskipun total nol. |
| U-P06 | Buka dashboard sebelum dan sesudah tanggal penutupan | Pinjaman hanya keluar sejak tanggal efektif; grafik dan total konsisten. |
| U-P07 | Periksa saldo negatif terbuka dan saldo tidak diketahui | Negatif terpisah dari pie/KPI; unknown tetap null dan punya reason di rincian berizin. |
| U-P08 | Ubah catatan tanpa mengubah sektor/wilayah; periksa tahun historis | Kategori dengan bukti tidak hilang karena `updated_at` kolom lain. |
| U-P09 | Cek nilai tooltip serta jumlah sektor, kualitas, provinsi | Semua nominal rupiah dan jumlah masing-masing sama dengan subtotal KPI positif. |
| U-P10 | Pilih 2025/2026, coba 2024/tahun depan | Opsi dan validasi server benar; kosong tidak dipalsukan nol; carryover berlabel. |
| U-P11 | Dua admin menutup/menambah angsuran pada pinjaman sama | Tidak ada penutupan ganda atau pembayaran setelah lunas; memerlukan DB MySQL testing terpisah. |
| U-P12 | Buka rincian sebagai Admin PUMK, Super Admin, role lain | Dua role berhak dapat rincian dan kartu sesuai izin; role lain hanya agregat, tanpa nomor identitas. |
| U-P13 | Ulangi dashboard BRI/TJSL dan upload terkait | Fitur di luar PUMK internal tetap bekerja. |

Setelah migration di staging, verifikasi jumlah pinjaman, angsuran, dokumen, dan log sebelum/sesudah tetap sama; kolom pelunasan baru pada record lama tetap `NULL`. Scheduler rutin hanya menangkap akhir bulan sebelumnya. Reconcile `pumk:monitoring-capture --reconcile` hanya dilakukan secara eksplisit pada staging setelah hasil diagnosis dan periode terdampak ditinjau; perintah itu dapat merevisi detail snapshot lama, bukan mengarsip semua versinya. Rollback schema yang menghapus kolom metadata tidak aman setelah ada penutupan baru; utamakan perbaikan maju dan pemulihan dari backup teruji. Uji concurrency MySQL serta UAT manusia tidak dapat digantikan oleh tes SQLite in-memory.

### Catatan aktivasi lokal, 29 September 2026

- Koneksi efektif saat aktivasi: `APP_ENV=local`, MySQL `127.0.0.1:3306`, database `tjslinka`; konfigurasi tidak di-cache. Password tidak dicatat.
- Backup sebelum perubahan: `storage/backups/before-pumk-settlement-20260929-073650.sql`, 1.967.913 byte, SHA-256 `4046037821dc9b1e4ec9848fc9b320d13b242734e161f327f9783eaf4376119f`; dump selesai normal. Simpan secara terbatas karena berisi data asli.
- Dua migration `2026_09_28_000001_add_pumk_settlement_metadata` dan `2026_09_28_000002_create_pumk_classification_history` selesai (batch 40 dan 41). Migrasi kedua sempat gagal akibat nama indeks otomatis melampaui batas MySQL, kemudian dilanjutkan dengan nama pendek pada tabel baru yang terverifikasi kosong, tanpa menghapus data lama.
- Sebelum/sesudah: 395 pinjaman, 395 mitra, 95 angsuran; lima kolom nullable dan tabel kategori tersedia. Metadata pelunasan lama tetap null. Toleransi efektif lokal `10000.00` melalui `.env`, hanya untuk pengembangan/UAT, bukan kebijakan perusahaan.
- Tes terarah pada SQLite `:memory:` terpisah: 61 lulus, 469 assertion. Render dashboard PUMK INKA memakai DB lokal berhasil, dengan 394 pinjaman diketahui, 11 saldo negatif terbuka, dan jumlah nominal sektor/kualitas/provinsi sama dengan KPI piutang positif pada posisi yang diuji. Halaman login lokal merespons HTTP 200; halaman monitoring tanpa login mengarahkan ke login (302).
- Rangkaian tes lengkap pertama menemukan periode angsuran Februari dapat bergeser ke Maret jika proses berjalan pada tanggal 29–31. Parser bulan pada simpan/edit angsuran dibetulkan agar selalu mulai tanggal 1; tes regresi dibekukan pada 31 Januari dan lulus. Rangkaian lengkap setelah perbaikan: 245 tes lulus, 2.626 assertion.
- UAT manusia U-P01–U-P13 tetap **BELUM DIUJI**. Uji konkurensi MySQL **BLOCKED** karena belum ada database testing MySQL khusus; jangan menggunakan database `tjslinka` untuk skenario penutupan sintetis.
