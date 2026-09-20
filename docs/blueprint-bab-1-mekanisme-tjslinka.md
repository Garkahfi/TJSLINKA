# BAB 1 - MEKANISME SISTEM TJSLINKA

**Status:** Draft berbasis implementasi per 5 Agustus 2026  
**Ruang lingkup:** Program TJSL Internal dan Bantuan CSR  
**Ditunda:** Program Eksternal

> Bagian ini disusun untuk mendokumentasikan mekanisme yang telah terverifikasi pada aplikasi. Latar belakang, tujuan organisasi, dasar kebijakan, dan identitas pemilik proses tetap harus dilengkapi atau disahkan oleh mentor/pemilik proses.

## 1. Mekanisme Sistem TJSLINKA

Sistem TJSLINKA dirancang sebagai sarana pengelolaan Program Tanggung Jawab Sosial dan Lingkungan (TJSL) secara terstruktur, terdokumentasi, dan berbasis kewenangan pengguna. Dalam ruang lingkup pembahasan ini, sistem mengakomodasi dua kelompok proses utama, yaitu Program TJSL Internal dan Bantuan CSR. Program TJSL Internal dibedakan lebih lanjut berdasarkan jenis kerja sama PKS dan NON-PKS, sedangkan Program Eksternal belum dimasukkan karena proses bisnisnya masih akan dibahas secara terpisah.

Mekanisme pengelolaan dilaksanakan melalui dua fase pemeriksaan. Fase pertama mencakup penyusunan data pengajuan, pemenuhan dokumen awal, validasi sistem, serta keputusan persetujuan atau penolakan oleh Super Admin. Fase kedua dilaksanakan setelah dokumen awal memperoleh persetujuan dan mencakup pengunggahan, pemeriksaan, serta persetujuan dokumen Berita Acara Serah Terima (BAST) beserta dokumentasi pendukung.

Admin bertindak sebagai penyusun dan pengaju data sekaligus satu-satunya role yang membuat, mengubah, mengunggah, mengganti, atau menghapus konten pengajuan sesuai aturan kepemilikan dan status. Pada Program TJSL Internal, Admin memilih jenis kerja sama PKS atau NON-PKS, melengkapi informasi program, dan mengunggah dokumen yang diwajibkan. Program Internal jenis PKS menggunakan dokumen awal A sampai E, sedangkan NON-PKS menggunakan dokumen awal A dan B. Pada Bantuan CSR, Admin melengkapi identitas bantuan, pilar, target, rincian penerima dan bantuan, nilai anggaran, foto, serta dokumen awal A dan B.

Selama proses penyusunan, data dapat disimpan sebagai `draft`. Penyimpanan draft tetap mensyaratkan data utama lolos validasi, sedangkan dokumen wajib dapat dilengkapi sebelum pengajuan. Pengajuan hanya diteruskan ke fase pemeriksaan pertama apabila data dan dokumen wajib dinyatakan lengkap oleh sistem. Pengajuan yang berhasil divalidasi memperoleh status `pending_fase1`, dicatat pada status log, dan diteruskan melalui notifikasi dalam aplikasi kepada seluruh akun Super Admin yang aktif. Sebelum pemeriksaan dilakukan, Admin masih dapat membatalkan pengajuan dan mengembalikannya ke status `draft` untuk diperbaiki serta diajukan kembali.

Super Admin melakukan pemeriksaan secara read-only terhadap kesesuaian data dan kelengkapan dokumen awal. Super Admin tidak dapat mengubah atau menghapus isi pengajuan. Apabila pengajuan dinilai layak, Super Admin memberikan persetujuan dan sistem mengubah status menjadi `approved_fase1`. Status tersebut memberikan kewenangan kepada Admin untuk melanjutkan ke proses BAST. Apabila pengajuan ditolak, Super Admin wajib mencantumkan alasan penolakan dan sistem mengubah status menjadi `rejected_fase1`. Pada implementasi saat ini, penolakan fase pertama bersifat terminal sehingga pengajuan tidak dapat dilanjutkan ke fase BAST.

Pada fase kedua, Admin mengunggah dokumen F/BAST dan dokumentasi pendukung sesuai jenis proses. Sistem memvalidasi kepemilikan data, status, tipe file, ukuran file, dan keberadaan BAST. Pengajuan BAST yang valid memperoleh status `pending_fase2`, dicatat pada status log, dan diteruskan melalui notifikasi dalam aplikasi kepada seluruh akun Super Admin aktif untuk diperiksa. Apabila BAST diunggah ulang, file BAST lama diganti, sedangkan foto dokumentasi baru ditambahkan dan foto lama tidak dihapus secara otomatis.

Super Admin kemudian melihat dan memeriksa BAST secara read-only serta memberikan keputusan. Super Admin tidak dapat mengunggah, mengganti, atau menghapus BAST. Apabila BAST belum sesuai, Super Admin wajib mencantumkan alasan penolakan dan sistem mengembalikan status ke `approved_fase1`. Admin dapat memperbaiki atau mengganti BAST tanpa mengulang pengajuan dokumen awal. Apabila BAST dinyatakan sesuai, Super Admin memberikan persetujuan dan sistem menetapkan status `completed` sebagai penanda bahwa rangkaian Program TJSL Internal atau Bantuan CSR telah selesai.

Pada setiap perubahan status penting, sistem menyimpan informasi pelaku, waktu perubahan, status asal, status tujuan, dan catatan yang relevan. Sistem juga mengirimkan notifikasi kepada pengguna terkait serta memperbarui informasi pada halaman monitoring. Dengan mekanisme tersebut, proses pengajuan dan persetujuan dapat ditelusuri berdasarkan data, dokumen, status, reviewer, dan waktu pemrosesan.

### 1.1 Prinsip Pengendalian Proses

Mekanisme TJSLINKA menerapkan prinsip pengendalian sebagai berikut:

1. **Pemisahan kewenangan.** Admin membuat dan memperbaiki data/dokumen, sedangkan Super Admin hanya membaca, memeriksa, serta memberikan keputusan persetujuan atau penolakan. Penolakan wajib disertai alasan dan tidak memberi Super Admin kewenangan mengubah konten.
2. **Validasi berbasis status.** Setiap aksi hanya dapat dijalankan pada status yang sesuai dengan tahapan proses.
3. **Validasi dokumen.** Persetujuan fase pertama mensyaratkan dokumen awal lengkap dan persetujuan fase kedua mensyaratkan BAST tersedia.
4. **Kepemilikan data.** Admin hanya dapat mengelola Program Internal atau Bantuan CSR yang dibuat oleh akunnya sendiri.
5. **Pencatatan proses.** Perubahan status dicatat melalui status log dan data reviewer.
6. **Notifikasi.** Pengajuan dan keputusan penting disampaikan kepada pengguna terkait melalui notifikasi aplikasi.
7. **Perbaikan terbatas.** Penolakan BAST hanya mengulang fase BAST, sedangkan dokumen awal yang telah disetujui tidak diajukan kembali.

### 1.2 Status Utama

| Status | Makna dalam mekanisme |
|---|---|
| `draft` | Data masih disusun atau telah dikembalikan oleh Admin sebelum review. |
| `pending_fase1` | Dokumen awal telah diajukan dan menunggu pemeriksaan Super Admin. |
| `rejected_fase1` | Dokumen awal ditolak dan alur berakhir pada implementasi saat ini. |
| `approved_fase1` | Dokumen awal disetujui; Admin dapat mengunggah atau memperbaiki BAST. |
| `pending_fase2` | BAST telah diajukan dan menunggu pemeriksaan Super Admin. |
| `completed` | BAST disetujui dan rangkaian proses dinyatakan selesai. |

### 1.3 Diagram Alir Mekanisme

Diagram alir disusun menggunakan PlantUML dengan tiga swimlane: **Admin**, **Sistem**, dan **Super Admin**. Pemisahan ini menunjukkan dengan jelas pihak yang menginput data, fungsi yang dijalankan otomatis oleh aplikasi, dan pihak yang mengambil keputusan.

Sumber diagram PlantUML:

[mekanisme-tjslinka-internal-csr.puml](flowcharts/mekanisme-tjslinka-internal-csr.puml)

**Judul gambar yang disarankan:**  
*Gambar 1. Diagram Alir Mekanisme Program TJSL Internal dan Bantuan CSR pada Sistem TJSLINKA.*

### 1.4 Fungsi Pendukung di Luar Alur Persetujuan

Selain mekanisme pengajuan dan persetujuan, TJSLINKA memiliki fungsi pendukung berikut:

- dashboard Admin dan Super Admin untuk melihat ringkasan proses sesuai kewenangan;
- monitoring Program Internal dan Bantuan CSR yang telah memperoleh persetujuan awal;
- notifikasi proses bagi Admin dan Super Admin;
- pengelolaan Produk dan Paket Teras TJSL oleh Admin;
- pengelolaan akun Admin oleh Super Admin; dan
- sinkronisasi data dashboard dari Google Sheets untuk Pilar, Wilayah, Bidang Prioritas, dan TPB melalui scheduler aplikasi.

Fungsi pendukung tersebut tidak mengubah alur persetujuan utama. Khusus konten Teras TJSL, implementasi saat ini tidak memiliki tahap persetujuan Super Admin. Sementara itu, akses untuk mengubah Google Sheets berada di luar otorisasi aplikasi dan harus ditetapkan oleh pemilik proses.

### 1.5 Batasan Pembahasan

Mekanisme ini belum mencakup Program Eksternal, pengelolaan Form Survey Program Eksternal, serta keputusan bisnis yang belum disahkan oleh mentor. Apabila terdapat perubahan SOP, tingkat persetujuan, kebijakan penolakan, atau kewenangan edit dokumen, narasi dan diagram harus diperbarui agar tetap sesuai dengan implementasi dan kebijakan resmi.
