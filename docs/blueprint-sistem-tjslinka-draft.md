# BLUEPRINT SISTEM INFORMASI TJSL INKA

**Status dokumen:** Draft berbasis verifikasi kode (as-is)  
**Tanggal verifikasi:** 4 Agustus 2026  
**Nama aplikasi:** TJSLINKA / Sistem Informasi TJSL INKA  
**Referensi struktur:** *BLUEPRINT SURAT JALAN ONLINE (rev B)*  

> **Pembaruan ruang lingkup 5 Agustus 2026:** penyusunan dokumen resmi dimulai dari Bab 2 dan untuk sementara hanya mencakup Program TJSL Internal serta Bantuan CSR. Program Eksternal ditunda. Draf Bab 2 yang menjadi acuan kerja tersedia pada `docs/blueprint-bab-2-pengguna-otoritas-internal-csr.md`. Bagian Program Eksternal dalam dokumen induk ini merupakan hasil pemetaan awal dan belum termasuk ruang lingkup dokumen yang sedang disusun.

> Dokumen referensi hanya digunakan sebagai contoh susunan blueprint. Proses bisnis Surat Jalan Online, nama aktor, status, formulir, integrasi, dan spesifikasi infrastrukturnya tidak dipindahkan ke TJSLINKA.

## Cara membaca tingkat kepastian

| Penanda | Makna |
|---|---|
| **Terverifikasi** | Terdapat implementasi atau pengujian yang dapat ditelusuri pada repositori TJSLINKA. |
| **Draft narasi** | Rumusan dokumentasi yang disusun dari fungsi sistem, tetapi redaksi bisnisnya perlu disahkan pemilik proses. |
| **Perlu konfirmasi** | Tidak dapat dipastikan dari kode dan tidak boleh dianggap sebagai kondisi resmi. |
| **Gap / rencana** | Belum selesai, belum ditemukan, atau secara eksplisit disebut sebagai fitur lanjutan. |

---

## Riwayat Dokumen

| Tanggal | Nama | Jabatan/Peran | Status | Keterangan |
|---|---|---|---|---|
| 4 Agustus 2026 | **Perlu diisi** | Penyusun | Draft | Penyusunan awal berdasarkan verifikasi repositori aplikasi. |
| **Perlu diisi** | **Perlu diisi** | Pemilik Proses TJSL | Reviewed | Validasi proses bisnis, istilah, aktor, dan kewenangan. |
| **Perlu diisi** | **Perlu diisi** | Pejabat Berwenang | Approved | Pengesahan blueprint. |

## Daftar Isi yang Disarankan

1. Latar Belakang
2. Tujuan
3. Ruang Lingkup Blueprint
4. Batasan dan Asumsi
5. Kurun Waktu Capaian Keluaran
6. Gambaran Umum dan Mekanisme Sistem
7. Pengguna dan Otoritas Akses
8. Modul dan Tampilan Web
9. Alur Sistem dan Mekanisme Persetujuan
10. Data, Dokumen, Notifikasi, dan Audit Trail
11. Spesifikasi Teknis
12. Keamanan, Operasional, dan Pemulihan
13. Kriteria Penerimaan
14. Gap dan Keputusan yang Diperlukan
15. Lampiran Bukti Implementasi

---

## 1. Latar Belakang

**Draft narasi - perlu disahkan pemilik proses:**

Pengelolaan program Tanggung Jawab Sosial dan Lingkungan (TJSL) memerlukan data yang tertib, proses pengajuan dan persetujuan yang dapat ditelusuri, kelengkapan dokumen yang konsisten, serta informasi monitoring yang mudah dipahami oleh pihak terkait. TJSLINKA dikembangkan untuk mengelola Program TJSL Internal, Program Eksternal, Bantuan CSR, monitoring realisasi program, serta publikasi produk dan paket Teras TJSL dalam satu aplikasi berbasis web.

Sistem membedakan fungsi penginputan dan pengelolaan data oleh Admin, fungsi pemeriksaan dan pengambilan keputusan oleh Super Admin, serta fungsi pemantauan oleh pengguna dashboard yang telah terautentikasi. Perubahan status penting dicatat melalui status log dan hasil proses disampaikan melalui notifikasi kepada pihak terkait.

> **Perlu konfirmasi:** masalah pada proses lama, dasar kebijakan, unit pemilik aplikasi, sasaran organisasi, dan indikator keberhasilan belum terdokumentasi dalam repositori. Bagian tersebut perlu ditambahkan dari keterangan resmi PT INKA agar latar belakang tidak berisi asumsi.

## 2. Tujuan

### 2.1 Tujuan Penyusunan Blueprint

Dokumen Blueprint Sistem Informasi TJSL INKA disusun sebagai acuan dalam pengembangan, pengujian, penerapan, dan evaluasi sistem, khususnya untuk pengelolaan **Program TJSL Internal** dan **Bantuan CSR**. Blueprint ini mendokumentasikan batas fungsi sistem, pengguna dan kewenangannya, kebutuhan data dan dokumen, tampilan antarmuka, serta alur pengajuan dan persetujuan yang diterapkan pada sistem.

Blueprint ini juga digunakan untuk menyamakan pemahaman antara pemilik proses, Admin, Super Admin, pengembang, penguji, dan pengelola infrastruktur agar implementasi sistem sesuai dengan proses bisnis yang telah disetujui.

### 2.2 Tujuan Sistem

Berdasarkan fungsi yang telah terverifikasi pada aplikasi, Sistem Informasi TJSL INKA bertujuan untuk:

1. menyediakan pengelolaan data Program TJSL Internal dan Bantuan CSR secara terpusat;
2. mendukung proses pengajuan oleh Admin dan proses pemeriksaan serta pengambilan keputusan oleh Super Admin;
3. memastikan kelengkapan dokumen sesuai jenis pengajuan, yaitu Program Internal PKS, Program Internal NON-PKS, atau Bantuan CSR;
4. mendukung persetujuan dua fase, mulai dari pemeriksaan dokumen awal sampai pemeriksaan BAST;
5. menyediakan informasi status pengajuan dan kelengkapan dokumen untuk kebutuhan monitoring;
6. mencatat perubahan status, identitas reviewer, waktu pemeriksaan, dan alasan penolakan agar proses dapat ditelusuri; dan
7. menyampaikan notifikasi aplikasi kepada pengguna terkait ketika terjadi pengajuan, persetujuan, penolakan, permintaan perbaikan BAST, atau penyelesaian proses.

### 2.3 Hasil yang Diharapkan

**Draft tujuan bisnis - harus disahkan oleh mentor atau pemilik proses:**

Dengan diterapkannya Sistem Informasi TJSL INKA, pengelolaan Program TJSL Internal dan Bantuan CSR diharapkan menjadi lebih tertib, terdokumentasi, mudah dipantau, serta memiliki pembagian kewenangan yang jelas antara pihak yang mengajukan dan pihak yang memberikan keputusan. Sistem juga diharapkan membantu mengurangi ketidaklengkapan dokumen dan memudahkan penelusuran riwayat proses dari pengajuan awal sampai persetujuan BAST.

> Kata **diharapkan** digunakan karena manfaat organisasi tersebut belum dapat dibuktikan hanya dari source code. Klaim seperti “mempercepat proses sebesar 50%”, “menghilangkan seluruh kesalahan”, atau “meningkatkan efisiensi secara signifikan” tidak boleh dimasukkan tanpa data awal dan hasil pengukuran.

### 2.4 Indikator Keberhasilan

Bagian ini perlu diisi bersama mentor apabila blueprint diwajibkan memiliki sasaran terukur.

| Aspek | Indikator yang aman digunakan | Target resmi |
|---|---|---|
| Kelengkapan dokumen | Persentase pengajuan yang memenuhi dokumen wajib sebelum dikirim untuk review | **Perlu ditetapkan** |
| Ketertelusuran | Persentase perubahan status penting yang tercatat pada status log | **Perlu ditetapkan** |
| Waktu proses | Rata-rata waktu dari pengajuan sampai keputusan fase 1 dan fase 2 | **Perlu baseline dan target** |
| Perbaikan BAST | Jumlah atau persentase BAST yang dikembalikan untuk diperbaiki | **Perlu baseline dan target** |
| Penggunaan sistem | Persentase pengajuan Program Internal dan Bantuan CSR yang diproses melalui TJSLINKA | **Perlu ditetapkan** |
| Ketersediaan sistem | Persentase waktu layanan dapat diakses pada jam operasional | **Perlu persetujuan TI** |

### 2.5 Batas Bab Tujuan

Program Eksternal belum dimasukkan dalam tujuan blueprint versi ini dan akan dibahas pada revisi atau tahap berikutnya. Bab tujuan juga tidak menetapkan struktur jabatan organisasi, SLA, atau spesifikasi server karena ketiganya memerlukan keputusan resmi dari pemilik proses dan unit TI.

## 3. Ruang Lingkup Blueprint

### 3.1 Dalam ruang lingkup (terverifikasi)

1. autentikasi pengguna dashboard, Admin, dan Super Admin;
2. profil pengguna dan penggantian password;
3. dashboard kinerja TJSL;
4. pengelolaan Program TJSL Internal jenis PKS dan NON-PKS;
5. pengelolaan Program Eksternal melalui kajian dan tindak lanjut;
6. pengelolaan Bantuan CSR;
7. unggah dokumen, BAST, foto, dan dokumentasi tambahan sesuai jenis proses;
8. persetujuan dan penolakan bertahap oleh Super Admin;
9. monitoring Program Internal, Program Eksternal, dan Bantuan CSR;
10. status log dan notifikasi proses;
11. pengelolaan akun Admin oleh Super Admin;
12. pengelolaan produk dan paket Teras TJSL oleh Admin; dan
13. penayangan produk dan paket Teras TJSL yang berstatus aktif.

### 3.2 Di luar ruang lingkup atau belum terbukti

1. integrasi SAP, SIPETA, ERP, SSO, WhatsApp, atau sistem eksternal lain;
2. tanda tangan elektronik tersertifikasi;
3. pencetakan dokumen formal dengan nomor dokumen otomatis;
4. aplikasi mobile native;
5. formulir survei lanjutan Program Eksternal;
6. SLA persetujuan, eskalasi otomatis, dan delegasi pejabat;
7. spesifikasi server produksi, topologi jaringan, domain, TLS, backup, retensi, dan disaster recovery; dan
8. jadwal pengembangan serta tanggal go-live resmi.

## 4. Batasan dan Asumsi

1. Istilah **pengguna dashboard** berarti pengguna aktif yang berhasil login melalui guard `web`. Kode saat ini tidak membatasi halaman tersebut pada role khusus.
2. Role operasional yang terverifikasi adalah `admin` dan `super_admin`.
3. Super Admin tidak membuat, mengubah, mengunggah, mengganti, atau menghapus konten Program Internal maupun Bantuan CSR. Seluruh konten pengajuan bersifat read-only pada area Super Admin; role ini hanya memeriksa serta menyetujui atau menolak pada status yang sesuai.
4. Penolakan dokumen awal Program Internal dan Bantuan CSR merupakan keputusan akhir pada alur saat ini. Perbaikan BAST tidak mengulang dokumen awal.
5. Tombol, label, dan screenshot final harus diambil dari build yang telah disepakati agar tidak berbeda dari implementasi.
6. Tahun filter monitoring saat ini dibatasi pada 2024, 2025, dan 2026. Keputusan apakah daftar tahun harus dinamis masih diperlukan.

## 5. Kurun Waktu Capaian Keluaran

**Perlu konfirmasi - jangan menetapkan tanggal tanpa keputusan pemilik proyek.**

| No. | Kegiatan | Penanggung Jawab | Mulai | Selesai | Status |
|---:|---|---|---|---|---|
| 1 | Validasi blueprint as-is | Pemilik Proses + Pengembang | **Perlu diisi** | **Perlu diisi** | Belum disahkan |
| 2 | Penyempurnaan kebutuhan/gap | Pemilik Proses | **Perlu diisi** | **Perlu diisi** | Belum ditetapkan |
| 3 | UAT per role dan alur | Pengguna UAT + QA | **Perlu diisi** | **Perlu diisi** | Belum ditetapkan |
| 4 | Perbaikan hasil UAT | Pengembang | **Perlu diisi** | **Perlu diisi** | Belum ditetapkan |
| 5 | Persiapan infrastruktur dan keamanan | TI/Infrastruktur | **Perlu diisi** | **Perlu diisi** | Belum ditetapkan |
| 6 | Implementasi/go-live | Pemilik Sistem | **Perlu diisi** | **Perlu diisi** | Belum ditetapkan |

## 6. Gambaran Umum dan Mekanisme Sistem

### 6.1 Arsitektur fungsi

```text
Pengguna Dashboard ──> Dashboard, Overview, Rincian, Teras TJSL, Profil
                              ^
                              |
Admin ──> Pengajuan & pengelolaan data ──> Sistem ──> Review Super Admin
  ^                |                          |               |
  |                v                          v               v
  └──────── Notifikasi <──────────── Status Log ───── Keputusan
```

### 6.2 Kelompok proses utama

| Proses | Penginput/Pengelola | Reviewer | Hasil akhir |
|---|---|---|---|
| Program Internal PKS/NON-PKS | Admin | Super Admin fase 1 dan fase 2 | `completed` |
| Program Eksternal | Admin | Super Admin tahap 2 dan tahap 3 | `selesai_tahap3` |
| Bantuan CSR | Admin | Super Admin fase 1 dan fase 2 | `completed` |
| Teras TJSL | Admin | Tidak ada approval terverifikasi | Produk/paket aktif tampil pada halaman Teras TJSL |
| Akun Admin | Super Admin | Tidak ada approval terverifikasi | Akun aktif atau nonaktif |

## 7. Pengguna dan Otoritas Akses

### 7.1 Pengguna Dashboard Terautentikasi

**Hak akses terverifikasi:**

- login dan logout melalui halaman utama;
- melihat dashboard anggaran dan informasi TJSL;
- membuka Overview Program TJSL dan monitoring dokumen;
- mencari program dan memilih filter tahun yang tersedia;
- melihat rincian Program Internal yang memenuhi status publik;
- melihat produk dan paket Teras TJSL yang aktif; dan
- memperbarui profil serta password.

**Catatan:** sebutan organisasi pengguna ini perlu ditentukan. Jangan menuliskan “masyarakat umum” karena seluruh route utama saat ini berada di balik autentikasi.

### 7.2 Admin

**Hak akses terverifikasi:**

- login ke dashboard Admin;
- membuat Program Internal PKS atau NON-PKS;
- membuat Program Eksternal;
- membuat Bantuan CSR;
- menyimpan draft, mengajukan, melihat status, dan membatalkan pengajuan tertentu sebelum direview;
- mengunggah BAST dan dokumentasi tambahan setelah persetujuan awal;
- melihat notifikasi dan daftar status;
- mengunduh serta menghapus dokumen sesuai route dan aturan status;
- mengelola produk dan paket Teras TJSL, termasuk status aktif; dan
- mengelola profil serta password sendiri.

**Kewajiban yang disarankan untuk disahkan:** memastikan data, dokumen, nilai anggaran, foto, dan keterangan yang dimasukkan benar serta memiliki dasar yang dapat dipertanggungjawabkan.

### 7.3 Super Admin

**Hak akses terverifikasi:**

- login ke dashboard Super Admin;
- memeriksa Program Internal dan Bantuan CSR pada fase 1 dan fase 2;
- menyetujui atau menolak pengajuan dengan alasan penolakan;
- memeriksa Program Eksternal pada tahap kajian dan tindak lanjut;
- melihat serta mengunduh dokumen;
- melihat notifikasi;
- membuat, mengubah, dan menonaktifkan akun Admin; dan
- mengelola profil serta password sendiri.

**Batas terverifikasi:** Super Admin bukan pembuat atau editor konten dan bukan pengunggah BAST. Data, foto, dokumen awal, dan BAST hanya dapat dilihat/diunduh untuk pemeriksaan. BAST diunggah atau diganti oleh Admin. Jika pengajuan belum sesuai, Super Admin wajib menolak dengan alasan yang jelas agar perbaikan dilakukan oleh Admin.

### 7.4 Sistem

Sistem melakukan validasi autentikasi, role, kepemilikan data, status, kelengkapan input, tipe/ukuran file, perubahan status, pencatatan reviewer dan waktu, status log, serta pengiriman notifikasi aplikasi.

## 8. Modul dan Tampilan Web

> Screenshot final belum dimasukkan. Setiap gambar harus diberi nomor, nama halaman, URL/route, role, kondisi status, dan tanggal build.

### 8.1 Halaman login

Terdapat halaman login terpisah untuk pengguna dashboard (`/login`), Admin (`/admin/login`), dan Super Admin (`/superadmin/login`). Kredensial divalidasi terhadap akun aktif. Login Admin dan Super Admin juga memeriksa kecocokan role.

### 8.2 Dashboard pengguna

Dashboard menampilkan informasi realisasi TJSL yang bersumber dari Program Internal berstatus `completed`, termasuk rencana dan realisasi per pilar, persentase penyerapan, wilayah operasional, dan bidang prioritas. Konten PUMK dan FAQ juga tersedia pada tampilan.

### 8.3 Overview Program TJSL

Overview menggabungkan:

- Program Internal berstatus `approved_fase1`, `pending_fase2`, atau `completed`;
- Program Eksternal berstatus `pending_tahap3` atau `selesai_tahap3`; dan
- Bantuan CSR berstatus `approved_fase1`, `pending_fase2`, atau `completed`.

Tampilan menyediakan pencarian, pengelompokan berdasarkan pilar, checklist dokumen A-F, identitas jenis program, serta pembaruan monitoring otomatis. Draft, proses review awal, dan penolakan tidak ditampilkan pada Overview.

### 8.4 Rincian Program TJSL

Halaman rincian menampilkan Program Internal yang tidak diarsipkan dan berada pada status publik. Detail mencakup informasi program, anggaran, galeri, serta daftar dokumen yang tersedia.

### 8.5 Program Internal - Admin

Admin memilih Program Internal, kemudian memilih jenis kerja sama PKS atau NON-PKS. Form memuat data program, pilar, deskripsi, sasaran, lokasi, mitra, rencana/realisasi anggaran, tujuan, foto, dan dokumen yang berlaku. Admin dapat menyimpan sebagai draft atau mengajukan kepada Super Admin.

### 8.6 Program Eksternal - Admin dan Super Admin

Admin mengisi Form Kajian Proposal yang antara lain memuat nomor registrasi, tanggal diterima, asal proposal, kategori instansi, nomor surat pengantar, perihal, kebutuhan, jenis bantuan, kebutuhan dana, sifat pengajuan, contact person, rekening, kesesuaian RKA, pilar, TPB, dan program. Super Admin mengisi hasil kajian dan tindak lanjut pada tahap berikutnya.

### 8.7 Bantuan CSR - Admin

Form Bantuan CSR mengelola nama program bantuan, deskripsi, pilar, rencana/realisasi anggaran, target, rincian bantuan, foto, serta dokumen A dan B. Setelah persetujuan awal, Admin dapat mengunggah dokumen F/BAST dan dokumentasi tambahan.

### 8.8 Status dan monitoring

Admin dan Super Admin memiliki tampilan daftar status untuk Program TJSL dan Bantuan CSR. Halaman, menu, route, dan aksi arsip tidak tersedia pada dashboard; monitoring historis tetap dipusatkan pada halaman monitoring publik. Penanda `is_archived` dipertahankan hanya untuk kompatibilitas data lama dan bukan fitur yang dapat dijalankan pengguna.

### 8.9 Notifikasi

Notifikasi aplikasi menyampaikan pengajuan baru, hasil persetujuan, penolakan, kebutuhan perbaikan BAST, dan penyelesaian proses. Status baca dan jumlah notifikasi belum dibaca tersedia.

### 8.10 Manajemen User

Super Admin dapat membuat dan mengubah akun Admin serta menonaktifkan akun. Form akun memuat nama, username, email, jabatan, nomor telepon, alamat, dan password sementara saat pembuatan.

### 8.11 Teras TJSL

Admin dapat mengelola:

- **Produk:** nama produk, nama UMKM, foto, deskripsi, dan status aktif;
- **Paket:** nama paket, foto, harga, tipe harga, isi paket, catatan khusus, urutan, dan status aktif.

Halaman pengguna hanya menampilkan produk dan paket yang aktif.

### 8.12 Profil

Pengguna dapat mengubah informasi profil dan password. Password Admin dan Super Admin disyaratkan minimal delapan karakter serta mengandung huruf dan angka.

## 9. Alur Sistem dan Mekanisme Persetujuan

### 9.1 Program Internal PKS/NON-PKS

| Tahap | Aktor | Status awal | Aksi | Status hasil | Keterangan |
|---|---|---|---|---|---|
| Penyusunan | Admin | - / `draft` | Simpan draft | `draft` | Data dapat dilengkapi kembali oleh pembuat. |
| Pengajuan awal | Admin | `draft` | Simpan dan ajukan | `pending_fase1` | Sistem memvalidasi data dan dokumen wajib. |
| Pembatalan sebelum review | Admin | `pending_fase1` | Batalkan | `draft` | Pengajuan dapat diedit dan diajukan kembali. |
| Review fase 1 | Super Admin | `pending_fase1` | Setujui | `approved_fase1` | Admin memperoleh akses unggah BAST. |
| Review fase 1 | Super Admin | `pending_fase1` | Tolak + alasan | `rejected_fase1` | Penolakan awal mengakhiri alur saat ini. |
| Pengajuan BAST | Admin | `approved_fase1` | Unggah F/BAST | `pending_fase2` | BAST menunggu pemeriksaan. |
| Review fase 2 | Super Admin | `pending_fase2` | Tolak + alasan | `approved_fase1` | Admin memperbaiki BAST; dokumen awal tidak diulang. |
| Review fase 2 | Super Admin | `pending_fase2` | Setujui | `completed` | Proses selesai. |

**Dokumen Program Internal:**

| Kode | Dokumen | PKS | NON-PKS |
|---|---|:---:|:---:|
| A | Proposal Pengajuan Program | Wajib | Wajib |
| B | Kelengkapan Survei | Wajib | Wajib |
| C | Kajian Kelayakan Kerja Sama | Wajib | Tidak berlaku |
| D | Kajian Risiko | Wajib | Tidak berlaku |
| E | Perjanjian Kerja Sama | Wajib | Tidak berlaku |
| F | BAST | Fase 2 | Fase 2 |

### 9.2 Bantuan CSR

Alurnya setara dengan dua fase Program Internal:

```text
draft
  -> pending_fase1
      -> rejected_fase1 (akhir), atau
      -> approved_fase1
          -> pending_fase2
              -> approved_fase1 (BAST ditolak dan diperbaiki), atau
              -> completed
```

Dokumen awal yang berlaku adalah A (Proposal Pengajuan Program) dan B (Kelengkapan Survei). Dokumen F/BAST diproses setelah persetujuan awal. Target, rincian bantuan, foto, dan dokumentasi tambahan mengikuti validasi form.

### 9.3 Program Eksternal

| Tahap | Aktor | Status awal | Aksi | Status hasil | Keterangan |
|---|---|---|---|---|---|
| Penyusunan | Admin | - / `draft` | Simpan | `draft` | Form masih dapat diedit oleh pembuat. |
| Pengajuan kajian | Admin | `draft` | Ajukan | `pending_tahap2` | Notifikasi dikirim ke Super Admin. |
| Kajian | Super Admin | `pending_tahap2` | Tolak + alasan | `ditolak_tahap2` | Proses berhenti pada implementasi saat ini. |
| Kajian | Super Admin | `pending_tahap2` | Lanjutkan | `pending_tahap3` | Super Admin mengisi data analisa kajian. |
| Tindak lanjut | Super Admin | `pending_tahap3` | Selesaikan | `selesai_tahap3` | Hasil tindak lanjut dan kebutuhan survei dicatat. |

> **Gap terverifikasi:** bila `survey_diperlukan = iya`, sistem hanya menampilkan pesan bahwa Form Survey merupakan fitur menyusul. Belum terdapat alur survei lengkap.

### 9.4 Teras TJSL

Tidak ditemukan mekanisme approval. Admin dapat membuat, memperbarui, menghapus, dan mengubah status aktif. Item aktif ditampilkan pada halaman Teras TJSL pengguna.

## 10. Data, Dokumen, Notifikasi, dan Audit Trail

### 10.1 Entitas utama

- User;
- Pilar;
- Bidang Prioritas;
- Wilayah Operasional;
- Program Internal, dokumen, dan foto;
- Program Eksternal;
- Bantuan CSR, target, rincian, dokumen, dan foto;
- Produk serta Paket Teras TJSL;
- Notifikasi Admin; dan
- Status Log.

### 10.2 Audit trail

Perubahan status utama dicatat dengan jenis entitas, ID data terkait, status asal, status tujuan, pengguna yang melakukan perubahan, catatan, dan waktu. Nama reviewer dan waktu review juga disimpan pada entitas proses.

### 10.3 Dokumen dan file

File disimpan melalui storage aplikasi. Validasi tipe dan ukuran berbeda menurut form. Batas final, daftar MIME, kebijakan retensi, klasifikasi kerahasiaan, antivirus, serta prosedur pemusnahan dokumen harus ditetapkan pada dokumen operasional.

## 11. Spesifikasi Teknis

### 11.1 Stack aplikasi yang terverifikasi

| Komponen | Kondisi terverifikasi |
|---|---|
| Jenis aplikasi | Web berbasis Laravel |
| Bahasa backend | PHP, requirement proyek `^8.3` |
| Framework backend | Laravel `^13.8` |
| ORM dan migrasi | Laravel Eloquent dan Laravel Migrations |
| Frontend build | Vite `^8.0` |
| CSS | Tailwind CSS `^4.0` |
| JavaScript | JavaScript module melalui Vite |
| Database | Skema menggunakan database relasional; MySQL disebut pada flowchart, tetapi database produksi perlu dikonfirmasi |
| Penyimpanan file | Laravel Storage, termasuk disk publik untuk aset Teras TJSL |
| Pengujian | PHPUnit `^12.5.12` dan feature tests Laravel |

### 11.2 Infrastruktur produksi - perlu keputusan TI

| Komponen | Status |
|---|---|
| Web server/reverse proxy | **Perlu konfirmasi** |
| Sistem operasi server | **Perlu konfirmasi** |
| Versi database produksi | **Perlu konfirmasi** |
| CPU, RAM, dan storage | **Perlu sizing berdasarkan beban** |
| Domain dan sertifikat TLS | **Perlu konfirmasi** |
| SMTP/notifikasi email | **Perlu konfirmasi operasional** |
| Queue worker dan process supervisor | **Perlu konfirmasi** |
| Backup, retensi, restore test | **Perlu konfirmasi** |
| Monitoring, logging, dan alerting | **Perlu konfirmasi** |

> Angka server dari blueprint Surat Jalan Online tidak digunakan karena tidak ada bukti bahwa beban, arsitektur, dan kebutuhan penyimpanan kedua aplikasi sama.

## 12. Keamanan, Operasional, dan Pemulihan

### 12.1 Kontrol yang terverifikasi

- autentikasi terpisah untuk pengguna dashboard, Admin, dan Super Admin;
- pemeriksaan akun aktif saat login;
- pemeriksaan role untuk area Admin dan Super Admin;
- regenerasi session setelah login;
- pembatasan kepemilikan pada data Program Eksternal Admin;
- validasi status sebelum aksi sensitif;
- validasi input dan file pada controller; dan
- pencatatan perubahan status.

### 12.2 Hal yang wajib diputuskan sebelum go-live

1. kebijakan password, reset password, lockout, dan masa berlaku session;
2. MFA untuk role berwenang;
3. matriks klasifikasi dan retensi dokumen;
4. backup, restore test, RPO, dan RTO;
5. log akses serta pemantauan aktivitas berisiko;
6. pemindaian malware pada upload;
7. batas ukuran penyimpanan per entitas/pengguna;
8. SLA dukungan dan alur penanganan insiden; dan
9. prosedur pembuatan, perubahan, penonaktifan, dan review berkala akun.

### 12.3 Temuan implementasi yang perlu ditinjau

1. Middleware bernama `EnsureAdminPasswordChanged` saat ini memeriksa role, tetapi belum memaksa nilai `must_change_password` menjadi `false` sebelum akses fitur lain.
2. Filter tahun monitoring masih ditulis tetap untuk 2024-2026.
3. Form survei Program Eksternal belum tersedia.
4. Dokumentasi README proyek masih berupa README bawaan Laravel dan belum menjadi panduan operasional TJSLINKA.

## 13. Kriteria Penerimaan yang Disarankan

### 13.1 Autentikasi dan otorisasi

- akun tidak aktif tidak dapat login;
- Admin tidak dapat masuk sebagai Super Admin dan sebaliknya;
- pengguna tidak dapat menjalankan aksi di luar role dan status yang diizinkan;
- akses langsung melalui URL tetap ditolak bila tidak berwenang.

### 13.2 Program Internal dan Bantuan CSR

- dokumen wajib mengikuti jenis proses;
- draft tidak masuk antrean review;
- persetujuan dan penolakan mengubah status secara atomik;
- alasan penolakan tersimpan dan terlihat oleh Admin terkait;
- penolakan BAST mengembalikan proses hanya ke tahap perbaikan BAST;
- halaman pemeriksaan Super Admin tidak menyediakan aksi edit, unggah, ganti, atau hapus konten pada status apa pun;
- hanya Admin yang dapat mengunggah atau mengganti BAST pada status yang diizinkan;
- penolakan fase 1 dan fase 2 oleh Super Admin mewajibkan alasan;
- status log dan notifikasi tercatat pada setiap transisi penting.

### 13.3 Program Eksternal

- hanya pembuat yang dapat mengubah draft miliknya;
- Super Admin hanya dapat memproses status yang sesuai;
- hasil kajian dan tindak lanjut tersimpan beserta reviewer dan waktu;
- UI tidak menyatakan Form Survey tersedia sebelum fiturnya benar-benar dibuat.

### 13.4 Monitoring

- hanya status yang disetujui untuk monitoring yang ditampilkan;
- checklist A-F mengikuti jenis Program Internal, Eksternal, atau CSR;
- perubahan data tampil tanpa merusak filter pencarian;
- angka dashboard hanya memakai sumber data dan status yang telah disepakati.

## 14. Gap dan Keputusan yang Diperlukan

| Prioritas | Keputusan | Mengapa diperlukan |
|---|---|---|
| Tinggi | Nama resmi sistem dan pemilik proses | Menentukan judul, otoritas, dan pengesahan dokumen. |
| Tinggi | Siapa yang berhak menjadi pengguna dashboard | Kode hanya mensyaratkan akun aktif, belum role khusus. |
| Tinggi | Definisi bisnis Program Internal, Program Eksternal, dan Bantuan CSR | Dibutuhkan agar latar belakang dan aturan proses resmi. |
| Tinggi | Nasib pengajuan yang ditolak pada fase/tahap awal | Implementasi saat ini memperlakukan penolakan sebagai akhir. |
| Tinggi | Kebijakan dokumen, file, dan data pribadi | Menentukan keamanan, retensi, akses, dan audit. |
| Tinggi | Infrastruktur produksi dan pemulihan | Belum dapat ditentukan dari source code. |
| Sedang | Alur Form Survey Program Eksternal | Fitur disebut menyusul dan belum diimplementasikan. |
| Sedang | Filter tahun monitoring dinamis atau tetap | Saat ini hanya 2024-2026. |
| Sedang | Approval untuk konten Teras TJSL | Saat ini Admin dapat menayangkan item aktif tanpa review. |
| Sedang | Mekanisme wajib ganti password pertama | Field tersedia, tetapi middleware belum menegakkannya. |
| Rendah | Format cetak/ekspor laporan | Belum ditemukan sebagai fungsi inti pada route. |

## 15. Lampiran Bukti Implementasi

Dokumen ini disusun dari sumber berikut:

- `routes/web.php` - daftar route dan pembatasan middleware;
- `app/Models/Program.php` - Program Internal, jenis kerja sama, dokumen, dan status publik;
- `app/Models/ProgramEksternal.php` - status tiga tahap Program Eksternal;
- `app/Models/BantuanCsr.php` - dokumen dan status Bantuan CSR;
- `app/Http/Controllers/` - validasi, hak aksi, dan transisi proses;
- `app/Services/ProgramMonitoringService.php` - normalisasi monitoring A-F;
- `database/migrations/` - struktur data;
- `resources/views/` - halaman dan elemen antarmuka;
- `tests/Feature/` - perilaku yang diuji; dan
- `docs/flowcharts/` - diagram alur operasional yang sudah tersedia.

## 16. Checklist Pengesahan Blueprint

- [ ] Judul dan nama resmi aplikasi disetujui.
- [ ] Latar belakang dan tujuan disahkan pemilik proses.
- [ ] Semua aktor dipetakan ke jabatan/unit organisasi nyata.
- [ ] Matriks hak akses disetujui.
- [ ] Semua status dan transisi disetujui.
- [ ] Daftar dokumen A-F serta definisinya disetujui.
- [ ] Screenshot diambil dari build final dan diberi nomor gambar.
- [ ] Jadwal implementasi disetujui.
- [ ] Spesifikasi infrastruktur disetujui TI.
- [ ] Kebijakan keamanan, backup, retensi, dan insiden disetujui.
- [ ] UAT selesai dan bukti hasil pengujian dilampirkan.
- [ ] Dokumen ditandatangani reviewer dan approver.
