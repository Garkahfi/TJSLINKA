# BAB 2 - PENGGUNA DAN OTORITAS AKSES SISTEM TJSLINKA

**Status:** Draft berdasarkan implementasi per 2 September 2026  
**Aktor yang dibahas:** Admin dan Super Admin  
**Fungsi yang dibahas:** Program TJSL Internal, Bantuan CSR, Teras TJSL, akun, profil, notifikasi, dashboard role, serta sumber data dashboard  
**Ditunda:** seluruh kewenangan dan alur Program Eksternal

**Dokumen terkait:** [Bab 1 - Mekanisme Sistem TJSLINKA](blueprint-bab-1-mekanisme-tjslinka.md)

> Bab ini mengikuti pola penyajian blueprint referensi, tetapi seluruh kewenangan diambil dari route, controller, model, tampilan, dan pengujian TJSLINKA. Nama jabatan, unit organisasi, dan dasar kewenangan yang tidak terdapat dalam kode sengaja tidak ditebak.

## Petunjuk sebelum mengisi Bab 2

Gunakan tiga kategori berikut agar dokumen tidak menyesatkan:

| Kategori | Cara memperlakukannya |
|---|---|
| **Terverifikasi di sistem** | Boleh ditulis sebagai fungsi aplikasi saat ini. |
| **Harus diisi mentor/pemilik proses** | Isi berdasarkan struktur organisasi, SOP, SK, atau keputusan resmi. |
| **Perlu keputusan bisnis** | Sistem sudah memiliki perilaku tertentu, tetapi belum tentu perilaku tersebut sesuai dengan kebijakan perusahaan. |

### Informasi yang harus Anda minta kepada mentor

| No. | Informasi yang harus diisi | Cara mengisi yang benar |
|---:|---|---|
| 1 | Nama resmi aktor Admin | Gunakan jabatan atau fungsi organisasi, bukan nama orang. Contoh format: **Staf/Administrator TJSL pada [nama unit]**. |
| 2 | Nama resmi aktor Super Admin | Gunakan jabatan/fungsi yang benar-benar memiliki kewenangan review. Contoh format: **Reviewer/Penanggung Jawab TJSL pada [nama unit]**. |
| 3 | Unit kerja masing-masing aktor | Salin nama resmi divisi/departemen/bagian dari struktur organisasi. |
| 4 | Dasar pemberian kewenangan | Cantumkan nomor dan nama SOP, SK, pedoman, atau surat penunjukan jika tersedia. |
| 5 | Pembagian tugas Admin | Tentukan apakah Program Internal, Bantuan CSR, dan Teras TJSL dikelola oleh Admin yang sama atau kelompok berbeda. |
| 6 | Tingkat persetujuan | Pastikan apakah satu Super Admin sudah cukup atau secara SOP diperlukan beberapa tingkat persetujuan. |
| 7 | Batas status perubahan oleh Admin | Tentukan status apa saja yang masih memperbolehkan Admin mengubah atau menghapus konten. Super Admin tetap read-only terhadap konten pada semua status. |
| 8 | Kebijakan penolakan | Tentukan apakah penolakan dokumen awal bersifat final atau harus dapat diperbaiki. |
| 9 | Kebijakan publikasi Teras TJSL | Tentukan apakah konten aktif boleh langsung tayang atau harus melalui reviewer. |
| 10 | Pengelola Google Sheets dashboard | Tentukan unit yang menginput, memeriksa, dan mengesahkan angka dashboard. |
| 11 | Pengelola akun | Tentukan proses permintaan, persetujuan, penonaktifan, dan pengaktifan kembali akun. |
| 12 | Retensi dan audit | Tentukan berapa lama data, dokumen, notifikasi, dan status log disimpan. |

**Contoh di atas hanya menunjukkan format penulisan dan bukan fakta mengenai struktur PT INKA.**

---

## 2. Pengguna dan Otoritas Akses Sistem TJSLINKA

Dalam implementasi TJSLINKA, proses yang dibahas pada bab ini menggunakan dua role operasional, yaitu **Admin** dan **Super Admin**. Admin berperan sebagai satu-satunya pengelola dan pengaju konten Program TJSL Internal dan Bantuan CSR. Super Admin berperan sebagai pemeriksa dan pengambil keputusan pada tahapan persetujuan. Seluruh data, foto, dokumen awal, dan BAST pada halaman pemeriksaan Super Admin bersifat read-only. Hak akses juga dipengaruhi oleh kepemilikan data, status pengajuan, serta kondisi aktif atau tidak aktifnya akun.

Sistem menjalankan validasi akses, menyimpan perubahan status, mencatat reviewer dan waktu pemeriksaan, serta mengirimkan notifikasi. Keputusan layak atau tidak layak tetap dilakukan oleh Super Admin, bukan oleh sistem.

> **Harus diisi mentor:** nama resmi kedua aktor, unit kerja, jabatan yang diperbolehkan, dan dasar kewenangannya.

## 2.1 Admin

### 2.1.1 Identitas organisasi Admin

| Informasi | Isian |
|---|---|
| Nama role pada aplikasi | Admin (`admin`) - **terverifikasi** |
| Nama aktor/jabatan resmi | **[DIISI MENTOR/PEMILIK PROSES]** |
| Unit/divisi/departemen | **[DIISI MENTOR/PEMILIK PROSES]** |
| Cakupan tugas | **[Program Internal/Bantuan CSR/Teras TJSL/kombinasi]** |
| Pihak yang mengusulkan akun | **[DIISI MENTOR/PEMILIK PROSES]** |
| Pihak yang menyetujui akun | **[DIISI MENTOR/PEMILIK PROSES]** |
| Dasar kewenangan | **[SOP/SK/pedoman/surat penunjukan]** |

### 2.1.2 Deskripsi peran Admin

Naskah berikut dapat digunakan setelah identitas organisasi disahkan:

> Admin merupakan pengguna yang berwenang mengelola data operasional TJSLINKA sesuai penugasannya. Dalam ruang lingkup bab ini, Admin dapat menyusun dan mengajukan Program TJSL Internal dan Bantuan CSR, menindaklanjuti hasil review, mengunggah BAST, mengelola konten Teras TJSL, melihat notifikasi, serta memperbarui profil dan password sendiri. Kewenangan Admin dibatasi oleh role, kepemilikan data, dan status proses.

### 2.1.3 Kewenangan umum Admin

Kewenangan berikut **terverifikasi di sistem**:

- login melalui area Admin menggunakan akun aktif dengan role `admin`;
- membuka dashboard Admin;
- melihat ringkasan Program Internal miliknya dan jumlah program berdasarkan pilar;
- melihat ringkasan Bantuan CSR miliknya;
- melihat notifikasi miliknya;
- melihat jumlah notifikasi yang belum dibaca;
- membuka halaman notifikasi, yang sekaligus menandai seluruh notifikasi belum dibaca menjadi sudah dibaca;
- memperbarui nama depan, nama belakang, email, nomor telepon, jabatan, alamat, dan foto profil sendiri;
- mengganti password dengan memasukkan password saat ini, password baru, dan konfirmasi password;
- logout dari area Admin.

Password baru divalidasi minimal delapan karakter serta harus mengandung huruf dan angka.

### 2.1.4 Kewenangan Admin - Program TJSL Internal

Kewenangan berikut **terverifikasi di sistem**:

- melihat daftar Program Internal yang dibuat oleh akun Admin tersebut;
- memfilter daftar program berdasarkan pilar;
- memilih jenis Program Internal PKS atau NON-PKS;
- membuat Program Internal;
- mengisi pilar, nama program, deskripsi, sasaran, lokasi, mitra, rencana anggaran, realisasi anggaran, dan tujuan program;
- mengunggah dokumen dan foto sesuai validasi sistem;
- menyimpan data sebagai `draft`;
- mengubah data dan menghapus dokumen ketika program masih dapat diedit;
- mengajukan dokumen awal sehingga status berubah menjadi `pending_fase1` apabila dokumen wajib lengkap;
- membatalkan pengajuan berstatus `pending_fase1` sehingga kembali menjadi `draft`;
- membuka detail Program Internal miliknya;
- mengunduh dokumen Program Internal miliknya;
- membuka halaman BAST pada status `approved_fase1`, `pending_fase2`, atau `completed`;
- mengunggah atau mengganti dokumen F/BAST pada status `approved_fase1`;
- mengunggah maksimal sepuluh foto dokumentasi tambahan pada pengajuan BAST; dan
- mengajukan BAST kepada Super Admin sehingga status berubah menjadi `pending_fase2`.

#### Dokumen Program Internal

| Kode | Nama dokumen pada sistem | PKS | NON-PKS |
|---|---|:---:|:---:|
| A | Proposal Pengajuan Program | Wajib fase 1 | Wajib fase 1 |
| B | Kelengkapan Survei | Wajib fase 1 | Wajib fase 1 |
| C | Kajian Kelayakan Kerja Sama | Wajib fase 1 | Tidak berlaku |
| D | Kajian Risiko | Wajib fase 1 | Tidak berlaku |
| E | Perjanjian Kerja Sama | Wajib fase 1 | Tidak berlaku |
| F | BAST | Fase 2 | Fase 2 |

Dokumen A-E menerima file PDF, DOC, DOCX, XLS, atau XLSX dengan batas implementasi 10 MB per file. Foto menerima file gambar dengan batas implementasi 20 MB per file. Batas tersebut merupakan aturan aplikasi saat ini dan tetap perlu disahkan sebagai kebijakan dokumen.

### 2.1.5 Kewenangan Admin - Bantuan CSR

Kewenangan berikut **terverifikasi di sistem**:

- melihat daftar Bantuan CSR yang dibuat oleh akun Admin tersebut;
- membuat Bantuan CSR;
- mengisi nama program bantuan, deskripsi, pilar, rencana anggaran, dan realisasi anggaran;
- mengisi minimal satu target;
- mengisi minimal satu rincian bantuan;
- mengisi rincian kegiatan, penerima bantuan, jenis bantuan, kualitas/kuantitas, nominal bantuan, foto, dan keterangan foto;
- mengunggah dokumen awal A dan B;
- menyimpan Bantuan CSR sebagai `draft`;
- mengubah data dan dokumen awal selama status masih `draft`;
- mengajukan dokumen awal sehingga status berubah menjadi `pending_fase1` apabila dokumen A dan B lengkap;
- membatalkan pengajuan berstatus `pending_fase1` sehingga kembali menjadi `draft`;
- membuka detail Bantuan CSR miliknya;
- mengunduh dokumen Bantuan CSR miliknya;
- membuka halaman BAST pada status `approved_fase1`, `pending_fase2`, atau `completed`;
- mengunggah atau mengganti dokumen F/BAST pada status `approved_fase1`;
- mengunggah maksimal sepuluh foto dokumentasi BAST beserta keterangannya; dan
- mengajukan BAST kepada Super Admin sehingga status berubah menjadi `pending_fase2`.

#### Dokumen Bantuan CSR

| Kode | Nama dokumen pada sistem | Tahap |
|---|---|---|
| A | Proposal Pengajuan Program | Wajib fase 1 |
| B | Kelengkapan Survei | Wajib fase 1 |
| F | BAST | Fase 2 |

### 2.1.6 Kewenangan Admin - Teras TJSL

Kewenangan berikut **terverifikasi di sistem**:

#### Produk Teras TJSL

- melihat daftar seluruh produk Teras TJSL;
- membuat produk;
- mengisi nama produk, nama UMKM, foto, deskripsi, dan status aktif;
- mengubah produk;
- mengaktifkan atau menonaktifkan penayangan produk; dan
- menghapus produk beserta foto tersimpan yang dikelola aplikasi.

#### Paket Teras TJSL

- melihat daftar seluruh paket Teras TJSL;
- membuat paket;
- mengisi nama paket, foto, harga, tipe harga (`tetap` atau `maksimal`), isi paket, catatan khusus, dan status aktif;
- mengubah paket;
- mengaktifkan atau menonaktifkan penayangan paket; dan
- menghapus paket beserta foto tersimpan yang dikelola aplikasi.

Item yang aktif langsung dapat ditampilkan pada halaman Teras TJSL. **Tidak ditemukan approval Super Admin untuk Produk atau Paket Teras TJSL.**

> **Perlu keputusan bisnis:** controller Teras TJSL tidak membatasi perubahan berdasarkan `created_by`. Secara teknis, setiap Admin dapat mengubah, menonaktifkan, atau menghapus item Teras TJSL yang dibuat Admin lain. Mentor harus memastikan apakah perilaku ini memang diizinkan.

### 2.1.7 Batas kewenangan Admin

Batas berikut **terverifikasi di sistem**:

- Admin hanya dapat membuka dan mengelola Program Internal serta Bantuan CSR miliknya sendiri.
- Admin tidak dapat menyetujui pengajuannya sendiri.
- Data utama Program Internal dan Bantuan CSR hanya dapat diubah oleh Admin ketika status `draft` dan data bukan arsip lama.
- Admin tidak dapat menetapkan status `approved_fase1` atau `completed`.
- Admin hanya dapat menghapus dokumen Program Internal ketika program masih `draft`.
- Admin dapat menghapus dokumen A/B Bantuan CSR ketika data masih `draft`.
- Pada tahap perbaikan BAST CSR, Admin dapat menghapus dokumen tambahan BAST lama (`bast_tambahan`) apabila data tersebut sudah ada, tetapi form saat ini tidak menyediakan unggah dokumen tambahan baru dan fungsi tersebut tidak dapat menghapus BAST utama.
- Tidak tersedia route untuk menghapus keseluruhan Program Internal atau Bantuan CSR.
- Menu dan route arsip Program Internal serta Bantuan CSR telah dihapus dari implementasi terbaru.
- Data lama masih memiliki penanda `is_archived`, tetapi tidak ada aksi pengguna untuk memindahkan data baru ke arsip.
- Penolakan fase pertama (`rejected_fase1`) mengakhiri alur pada implementasi saat ini.
- Penolakan BAST mengembalikan status ke `approved_fase1`, sehingga Admin hanya memperbaiki/mengunggah ulang BAST tanpa mengulang dokumen awal.
- Program Eksternal sengaja tidak dijelaskan pada bab ini.

### 2.1.8 Kewajiban Admin - usulan untuk disahkan

Bagian berikut merupakan **redaksi kebijakan yang disarankan**, bukan fakta dari source code:

- mengisi data secara lengkap, benar, dan sesuai dokumen sumber;
- memastikan pilar, sasaran/target, lokasi, mitra, anggaran, penerima, dan rincian telah benar sebelum diajukan;
- memastikan file sesuai jenis dokumen, sah, dapat dibuka, dan tidak rusak;
- menjaga kerahasiaan akun dan tidak membagikan kredensial;
- menindaklanjuti hasil review sesuai tahapan yang tersedia;
- memastikan BAST dan dokumentasi menggambarkan pelaksanaan sebenarnya;
- memastikan konten Teras TJSL telah memperoleh persetujuan publikasi sesuai kebijakan perusahaan; dan
- tidak mengubah atau menghapus data milik Admin lain tanpa kewenangan tertulis.

## 2.2 Super Admin

### 2.2.1 Identitas organisasi Super Admin

| Informasi | Isian |
|---|---|
| Nama role pada aplikasi | Super Admin (`super_admin`) - **terverifikasi** |
| Nama aktor/jabatan resmi | **[DIISI MENTOR/PEMILIK PROSES]** |
| Unit/divisi/departemen | **[DIISI MENTOR/PEMILIK PROSES]** |
| Jabatan minimal | **[DIISI MENTOR/PEMILIK PROSES]** |
| Dasar kewenangan | **[SOP/SK/pedoman/surat penunjukan]** |
| Jumlah tingkat approval | **[DIISI MENTOR/PEMILIK PROSES]** |

### 2.2.2 Deskripsi peran Super Admin

Naskah berikut dapat digunakan setelah identitas organisasi disahkan:

> Super Admin merupakan pengguna yang berwenang memantau, memeriksa, menyetujui, atau menolak Program TJSL Internal dan Bantuan CSR. Pemeriksaan dilakukan secara read-only pada fase dokumen awal dan fase BAST. Jika pengajuan belum sesuai, Super Admin wajib menolak dengan alasan yang dapat ditindaklanjuti; perbaikan konten tetap dilakukan oleh Admin. Super Admin juga mengelola akun Admin, melihat notifikasi, serta memperbarui profil dan password sendiri sesuai kewenangan yang ditetapkan.

### 2.2.3 Kewenangan umum Super Admin

Kewenangan berikut **terverifikasi di sistem**:

- login melalui area Super Admin menggunakan akun aktif dengan role `super_admin`;
- membuka dashboard Super Admin;
- melihat jumlah Program Internal yang menunggu review dan yang selesai;
- melihat jumlah Bantuan CSR yang menunggu review dan yang selesai;
- melihat ringkasan Program Internal berdasarkan pilar;
- melihat ringkasan Bantuan CSR terbaru beserta pembuatnya;
- melihat notifikasi miliknya;
- melihat jumlah notifikasi yang belum dibaca;
- membuka halaman notifikasi, yang sekaligus menandai seluruh notifikasi belum dibaca menjadi sudah dibaca;
- memperbarui profil sendiri;
- mengganti password sendiri; dan
- logout dari area Super Admin.

### 2.2.4 Kewenangan Super Admin - Program TJSL Internal

Kewenangan berikut **terverifikasi di sistem**:

- melihat Program Internal dari seluruh Admin yang masuk daftar aktif;
- memfilter daftar berdasarkan pilar dan status;
- membuka data program, pilar, identitas pembuat, foto, dan dokumen;
- memeriksa kelengkapan A-E untuk PKS atau A-B untuk NON-PKS;
- menyetujui pengajuan berstatus `pending_fase1` sehingga menjadi `approved_fase1`;
- menolak pengajuan berstatus `pending_fase1` sehingga menjadi `rejected_fase1`;
- memeriksa BAST dan dokumentasi pada status `pending_fase2`;
- menyetujui BAST sehingga status menjadi `completed` apabila BAST tersedia;
- menolak BAST sehingga status kembali menjadi `approved_fase1`;
- menulis alasan minimal lima karakter pada setiap penolakan;
- melihat seluruh data dan dokumen Program Internal secara read-only; dan
- mengunduh dokumen Program Internal untuk keperluan pemeriksaan.

Super Admin **tidak dapat** membuat, mengubah, mengunggah, mengganti, atau menghapus data, foto, dokumen awal, maupun BAST Program Internal. Ketika menemukan kekurangan, satu-satunya jalur koreksi adalah menolak pengajuan dengan alasan yang jelas agar Admin melakukan perbaikan.

### 2.2.5 Kewenangan Super Admin - Bantuan CSR

Kewenangan berikut **terverifikasi di sistem**:

- melihat Bantuan CSR dari seluruh Admin yang tidak berstatus `draft`, tidak berstatus `rejected_fase1`, dan bukan data arsip lama;
- membuka data pembuat, pilar, target, rincian, foto, dan dokumen;
- menyetujui pengajuan berstatus `pending_fase1` sehingga menjadi `approved_fase1`;
- menolak pengajuan berstatus `pending_fase1` sehingga menjadi `rejected_fase1`;
- memeriksa BAST dan dokumentasi pada status `pending_fase2`;
- menyetujui BAST sehingga status menjadi `completed` apabila BAST tersedia;
- menolak BAST sehingga status kembali menjadi `approved_fase1`;
- menulis alasan sebanyak 5 sampai 2.000 karakter pada setiap penolakan;
- melihat seluruh data dan dokumen Bantuan CSR secara read-only; dan
- mengunduh dokumen Bantuan CSR untuk keperluan pemeriksaan.

Super Admin **tidak dapat** membuat, mengubah, mengunggah, mengganti, atau menghapus data, target, rincian, foto, dokumen awal, maupun BAST Bantuan CSR. Ketika menemukan kekurangan, satu-satunya jalur koreksi adalah menolak pengajuan dengan alasan yang jelas agar Admin melakukan perbaikan.

### 2.2.6 Kewenangan Super Admin - manajemen akun Admin

Kewenangan berikut **terverifikasi di sistem**:

- melihat daftar akun dengan role Admin;
- membuat akun Admin;
- mengisi nama, username, email, jabatan, nomor telepon, alamat, dan password sementara;
- mengubah data profil akun Admin; dan
- menonaktifkan akun Admin.

Pembuatan akun menetapkan role `admin`, akun aktif, dan penanda `must_change_password = true`. Aksi hapus pada antarmuka tidak menghapus record user, tetapi mengubah akun menjadi tidak aktif.

**Batas implementasi saat ini:**

- Super Admin tidak dapat membuat akun Super Admin melalui menu ini.
- Tidak ditemukan fungsi pada menu ini untuk mengubah password akun Admin yang sudah ada.
- Tidak ditemukan route khusus untuk mengaktifkan kembali akun Admin yang sudah dinonaktifkan.
- Middleware bernama `admin.password.changed` belum memeriksa nilai `must_change_password`; middleware tersebut saat ini hanya memeriksa role Admin/Super Admin. Dengan demikian, kewajiban mengganti password sementara belum benar-benar dipaksakan oleh sistem.

### 2.2.7 Batas kewenangan Super Admin

- Super Admin tidak membuat Program Internal atau Bantuan CSR; URL pembuatan pada area Super Admin diarahkan kembali ke halaman status.
- Seluruh konten Program Internal dan Bantuan CSR bersifat read-only bagi Super Admin pada semua status.
- Super Admin tidak memiliki aksi untuk mengubah atau menghapus data, foto, dokumen awal, maupun BAST.
- Super Admin bukan pengunggah atau pengganti BAST; pada status `approved_fase1`, halaman hanya menginformasikan bahwa sistem menunggu Admin mengunggah BAST.
- Pada status `pending_fase2`, Super Admin hanya dapat melihat/mengunduh BAST serta memilih Setujui atau Tolak.
- Keputusan setuju/tolak hanya dapat dijalankan pada `pending_fase1` atau `pending_fase2`.
- Alasan penolakan wajib diisi ketika Super Admin memilih Tolak.
- Persetujuan fase pertama memerlukan dokumen awal lengkap.
- Persetujuan fase kedua memerlukan dokumen BAST.
- Tidak tersedia route untuk menghapus keseluruhan Program Internal atau Bantuan CSR.
- Tidak tersedia menu atau route arsip pada implementasi terbaru.
- Super Admin tidak memiliki route untuk mengelola Produk atau Paket Teras TJSL.
- Program Eksternal sengaja tidak dijelaskan pada bab ini.

### 2.2.8 Kewajiban Super Admin - usulan untuk disahkan

Bagian berikut merupakan **redaksi kebijakan yang disarankan**, bukan fakta dari source code:

- memeriksa data dan kelengkapan dokumen sebelum mengambil keputusan;
- memastikan keputusan sesuai kewenangan dan ketentuan perusahaan;
- menulis alasan penolakan secara jelas, objektif, dan dapat ditindaklanjuti;
- memastikan BAST sesuai pengajuan dan pelaksanaan;
- menjaga independensi pemeriksaan dan tidak mengubah atau menghapus konten pengajuan;
- menjaga kerahasiaan akun dan tidak menggunakan akun pihak lain;
- meninjau akun Admin secara berkala; dan
- menonaktifkan akun yang tidak lagi berhak berdasarkan prosedur resmi.

## 2.3 Sistem sebagai Pemroses Otomatis

Sistem bukan pengguna manusia, tetapi menjalankan fungsi otomatis berikut:

- memvalidasi username, password, status akun aktif, dan role saat login;
- memvalidasi kepemilikan Program Internal dan Bantuan CSR untuk akses Admin;
- memvalidasi status sebelum aksi pengajuan, pembatalan, upload BAST, persetujuan, dan penolakan;
- memvalidasi kelengkapan dokumen awal serta keberadaan BAST;
- memvalidasi data dan file;
- menyimpan data, dokumen, dan foto;
- mengubah status sesuai aksi yang sah;
- mencatat reviewer, waktu review, alasan penolakan, dan status log;
- mengirim notifikasi proses kepada pihak terkait; dan
- memperbarui tampilan monitoring ketika data berubah.

Keputusan kelayakan tetap menjadi tindakan Super Admin. Sistem hanya menegakkan syarat teknis yang telah diimplementasikan.

## 2.4 Fungsi Baru - Sinkronisasi Dashboard dari Google Sheets

### 2.4.1 Perilaku yang terverifikasi

Dashboard utama sekarang membaca cache database yang dapat diperbarui dari empat sumber Google Sheets berformat CSV:

1. Pilar;
2. Wilayah;
3. Bidang Prioritas; dan
4. TPB.

Sinkronisasi dijadwalkan setiap 15 menit dan dilindungi agar proses yang sama tidak tumpang tindih. Setiap sheet diproses secara independen. Jika URL belum diisi, respons HTTP gagal, header tidak lengkap, atau data tidak valid, sheet terkait dilewati dan cache lama dipertahankan. Kegagalan dicatat pada log aplikasi.

Dashboard Program PUMK tidak termasuk dalam sinkronisasi Google Sheets ini.

### 2.4.2 Batas kewenangan

- Tidak tersedia menu Admin atau Super Admin untuk mengubah URL Google Sheets.
- Tidak tersedia menu Admin atau Super Admin untuk menjalankan sinkronisasi secara manual melalui web.
- URL sumber diatur melalui konfigurasi lingkungan aplikasi.
- Sinkronisasi dijalankan melalui command `dashboard:sync-sheets` dan scheduler Laravel.
- Hak edit Google Sheets ditentukan di luar TJSLINKA melalui pengaturan akses Google Sheets.

Oleh karena itu, **jangan menuliskan bahwa Admin atau Super Admin otomatis menjadi pengelola Google Sheets**. Aktor pengelola sumber data harus ditetapkan oleh mentor atau pemilik proses.

### 2.4.3 Informasi yang harus diisi

| Informasi | Isian |
|---|---|
| Nama aktor pengelola sheet | **[DIISI MENTOR/PEMILIK PROSES]** |
| Unit/divisi | **[DIISI MENTOR/PEMILIK PROSES]** |
| Sheet yang boleh diubah | **[Pilar/Wilayah/Bidang Prioritas/TPB]** |
| Sumber angka resmi | **[DIISI: laporan/sistem/dokumen sumber]** |
| Pihak pemeriksa sebelum publikasi | **[DIISI MENTOR/PEMILIK PROSES]** |
| Frekuensi pembaruan bisnis | **[DIISI; jadwal teknis sistem 15 menit]** |
| Prosedur koreksi | **[DIISI MENTOR/PEMILIK PROSES]** |
| Penanggung jawab konfigurasi URL | **[DIISI TI/PENGELOLA APLIKASI]** |

Google Sheets merupakan sumber pembaruan, bukan database utama aplikasi. Dashboard membaca hasil sinkronisasi yang tersimpan pada database TJSLINKA.

## 2.5 Matriks Otoritas Akses

Keterangan: **Ya** = tersedia; **Terbatas** = tergantung kepemilikan/status; **Tidak** = tidak tersedia.

| Menu/Fungsi | Admin | Super Admin | Catatan |
|---|:---:|:---:|---|
| Login pada area masing-masing | Ya | Ya | Hanya akun aktif dan role yang sesuai. |
| Melihat dashboard role | Ya | Ya | Isi dashboard berbeda per role. |
| Membuat Program Internal | Ya | Tidak | Super Admin diarahkan ke halaman status. |
| Mengubah Program Internal | Terbatas | Tidak | Admin mengubah data miliknya sesuai status yang diizinkan; Super Admin read-only. |
| Mengajukan dokumen awal Internal | Ya | Tidak | Menjadi `pending_fase1`. |
| Membatalkan Internal `pending_fase1` | Terbatas | Tidak | Hanya Admin pemilik. |
| Menyetujui/menolak Internal fase 1 | Tidak | Ya | Hanya pada `pending_fase1`. |
| Mengunggah/memperbaiki BAST Internal | Terbatas | Tidak | Admin pemilik pada `approved_fase1`. |
| Menyetujui/menolak BAST Internal | Tidak | Ya | Hanya pada `pending_fase2`. |
| Membuat Bantuan CSR | Ya | Tidak | Super Admin diarahkan ke halaman status. |
| Mengubah Bantuan CSR | Terbatas | Tidak | Admin mengubah data miliknya sesuai status yang diizinkan; Super Admin read-only. |
| Mengajukan dokumen awal CSR | Ya | Tidak | A dan B harus lengkap. |
| Membatalkan CSR `pending_fase1` | Terbatas | Tidak | Hanya Admin pemilik. |
| Menyetujui/menolak CSR fase 1 | Tidak | Ya | Hanya pada `pending_fase1`. |
| Mengunggah/memperbaiki BAST CSR | Terbatas | Tidak | Admin pemilik pada `approved_fase1`. |
| Menyetujui/menolak BAST CSR | Tidak | Ya | Hanya pada `pending_fase2`. |
| Mengunduh dokumen | Terbatas | Ya | Admin hanya dokumen data miliknya. |
| Menghapus dokumen | Terbatas | Tidak | Admin dibatasi kepemilikan, jenis dokumen, dan status; Super Admin read-only. |
| Menghapus seluruh pengajuan | Tidak | Tidak | Route tidak tersedia. |
| Menggunakan menu arsip | Tidak | Tidak | Menu dan route sudah dihapus. |
| Mengelola Produk Teras TJSL | Ya | Tidak | Tidak ada approval Super Admin. |
| Mengelola Paket Teras TJSL | Ya | Tidak | Tidak ada approval Super Admin. |
| Melihat notifikasi sendiri | Ya | Ya | Membuka halaman menandai semua sebagai dibaca. |
| Mengelola akun Admin | Tidak | Ya | Buat, ubah profil, dan nonaktifkan. |
| Membuat akun Super Admin | Tidak | Tidak | Tidak tersedia melalui menu user. |
| Mengubah profil sendiri | Ya | Ya | Termasuk avatar melalui controller profil. |
| Mengganti password sendiri | Ya | Ya | Minimal 8 karakter, huruf, dan angka. |
| Mengelola Google Sheets | Bukan hak aplikasi | Bukan hak aplikasi | Ditetapkan melalui akses di luar TJSLINKA. |

## 2.6 Matriks Status dan Dampaknya terhadap Kewenangan

| Status | Makna pada implementasi | Kewenangan utama Admin | Kewenangan utama Super Admin |
|---|---|---|---|
| `draft` | Data masih disusun | Edit, lengkapi, hapus dokumen tertentu, dan ajukan | Lihat read-only; tidak melakukan approval |
| `pending_fase1` | Menunggu review dokumen awal | Lihat atau batalkan | Lihat read-only; setujui atau tolak dengan alasan wajib saat menolak |
| `rejected_fase1` | Dokumen awal ditolak | Tidak dapat diedit/diajukan ulang | Lihat read-only; tidak dilanjutkan ke fase 2 |
| `approved_fase1` | Dokumen awal disetujui | Unggah atau perbaiki BAST | Lihat read-only; menunggu Admin mengajukan BAST |
| `pending_fase2` | BAST menunggu review | Melihat status | Lihat BAST secara read-only; setujui atau tolak dengan alasan wajib saat menolak |
| `completed` | BAST disetujui dan proses selesai | Melihat hasil | Melihat hasil secara read-only |

Program Internal dan Bantuan CSR menggunakan pola status umum yang sama. Perbedaannya terletak pada data isian dan dokumen awal yang wajib.

## 2.7 Alur Ringkas Hubungan Kewenangan

```text
ADMIN
  Menyusun draft
      -> Mengajukan dokumen awal
          -> Menunggu keputusan Super Admin

SUPER ADMIN - FASE 1
  Menyetujui -> Admin memperoleh akses BAST
  Menolak    -> Proses berakhir pada implementasi saat ini

ADMIN - FASE 2
  Mengunggah/memperbaiki BAST
      -> Mengajukan BAST

SUPER ADMIN - FASE 2
  Menyetujui -> Proses completed
  Menolak    -> Kembali ke Admin untuk perbaikan BAST saja
```

## 2.8 Hal yang Wajib Diputuskan sebelum Bab 2 Disahkan

Pemisahan kewenangan pengelolaan konten **sudah diputuskan** dan bukan lagi pertanyaan terbuka: Admin membuat, mengubah, mengunggah, mengganti, dan menghapus konten sesuai aturan kepemilikan/status; Super Admin hanya membaca, memeriksa, serta menyetujui atau menolak. Penolakan wajib disertai alasan, dan seluruh perbaikan dilakukan oleh Admin.

1. Apa nama jabatan dan unit resmi untuk Admin?
2. Apa nama jabatan dan unit resmi untuk Super Admin?
3. Apakah Admin Program Internal, Admin Bantuan CSR, dan Admin Teras TJSL merupakan kelompok yang sama?
4. Apakah satu tingkat Super Admin sesuai dengan SOP persetujuan?
5. Apakah penolakan fase pertama harus bersifat final seperti implementasi saat ini?
6. Apakah konten Teras TJSL boleh langsung tayang ketika diaktifkan oleh Admin?
7. Apakah setiap Admin boleh mengubah dan menghapus item Teras TJSL milik Admin lain?
8. Bagaimana akun yang dinonaktifkan dapat diaktifkan kembali?
9. Apakah pergantian password sementara harus dipaksa sebelum Admin mengakses menu lain?
10. Siapa yang mengelola dan memeriksa empat Google Sheets sumber dashboard?
11. Apakah aktivitas melihat dan mengunduh dokumen serta perubahan konten oleh Admin harus dicatat dalam audit log?
12. Berapa lama data, dokumen, notifikasi, dan status log harus disimpan?
13. Bagaimana data lama dengan `is_archived = true` harus diperlakukan setelah menu arsip dihapus?

Jawaban harus berasal dari mentor, pemilik proses, SOP, atau pejabat berwenang. Perilaku aplikasi saat ini tidak otomatis menjadi kebijakan resmi perusahaan.

## 2.9 Bagian yang Sengaja Ditunda

Bab ini belum membahas:

- pengajuan Program Eksternal oleh Admin;
- review Program Eksternal oleh Super Admin;
- status dan form Program Eksternal;
- alur survei Program Eksternal; dan
- matriks dokumen Program Eksternal.

Bagian tersebut akan disusun setelah proses bisnis Program Eksternal dikonfirmasi.

## 2.10 Bukti Implementasi

Penyusunan ulang Bab 2 diperiksa terhadap:

- `routes/web.php` dan `routes/console.php`;
- controller autentikasi Admin dan Super Admin;
- middleware role dan password;
- dashboard Admin dan Super Admin;
- controller Program Internal Admin dan Super Admin;
- controller Bantuan CSR Admin dan Super Admin;
- controller Teras Produk dan Teras Paket;
- controller profil, notifikasi, dan manajemen user;
- model Program, Bantuan CSR, User, Produk, Paket, Pilar, dan TPB Dashboard;
- command `dashboard:sync-sheets` dan `config/dashboard.php`;
- migration terbaru;
- tampilan Admin dan Super Admin; serta
- feature tests yang terkait dengan autentikasi, approval, dokumen, dashboard, Teras TJSL, penghapusan arsip, dan sinkronisasi Google Sheets.
