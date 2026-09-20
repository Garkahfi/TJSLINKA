# Indeks Flowchart

## Diagram mekanisme utama blueprint

`mekanisme-tjslinka-internal-csr.puml` merupakan diagram end-to-end tingkat
blueprint untuk Program TJSL Internal dan Bantuan CSR. Diagram ini menyatukan
alur draft, pemeriksaan dokumen awal, pengajuan BAST, perbaikan BAST, dan
penyelesaian melalui tiga swimlane: Admin, Sistem, dan Super Admin. Program
Eksternal sengaja berada di luar ruang lingkup diagram.

## Empat diagram operasional tiga swimlane

Keempat diagram ini menggunakan tiga swimlane. Urutan lane mengikuti
aktor yang menjadi fokus agar perpindahan proses tetap pendek dan mudah
dibaca saat diimpor ke draw.io.

1. `internal-admin.puml`
   - Fokus: Admin login dan mengajukan Program Internal.
   - Urutan lane: Admin, Sistem, Super Admin.
2. `internal-superadmin.puml`
   - Fokus: Super Admin login dan mereview Program Internal.
   - Urutan lane: Super Admin, Sistem, Admin.
3. `csr-admin.puml`
   - Fokus: Admin login dan mengajukan Bantuan CSR.
   - Urutan lane: Admin, Sistem, Super Admin.
4. `csr-superadmin.puml`
   - Fokus: Super Admin login dan mereview Bantuan CSR.
   - Urutan lane: Super Admin, Sistem, Admin.

Poin keempat pada permintaan awal ditafsirkan sebagai "Super Admin login
+ CSR", karena "Super Admin login + Internal" sudah tercakup pada poin
kedua. Dengan demikian, keempat diagram membentuk dua pasangan yang
simetris.

## Prinsip pembacaan

- Sistem hanya memvalidasi, menyimpan status, mencatat log, dan mengirim
  notifikasi. Keputusan layak/tidak layak tetap dibuat oleh Super Admin.
- Dokumen awal Internal PKS adalah A-E.
- Dokumen awal Internal NON-PKS hanya A-B; C-E tidak berlaku.
- Dokumen awal CSR hanya A-B.
- Dokumen F adalah BAST. Admin baru dapat mengunggah dan mengajukannya
  setelah dokumen awal disetujui.
- Seluruh data, foto, dokumen awal, dan BAST bersifat read-only pada area
  Super Admin. Tidak ada aksi membuat, mengedit, mengunggah, mengganti,
  atau menghapus konten pada role tersebut.
- Super Admin tidak mengunggah BAST. Super Admin hanya melihat/mengunduh
  BAST untuk pemeriksaan lalu memilih Setujui atau Tolak.
- Penolakan oleh Super Admin wajib disertai alasan yang jelas. Jika konten
  perlu diperbaiki, Super Admin menolak pengajuan dan Admin yang melakukan
  perubahan serta pengajuan ulang sesuai status yang diizinkan.
- Sistem tidak membuat keputusan kelayakan. Sistem memvalidasi file,
  menyimpan keputusan Super Admin, dan mengubah status program.
- Keempat diagram utama memakai urutan BAST yang sama:
  Admin mengunggah -> Sistem memvalidasi teknis -> Super Admin menilai
  kelayakan -> Sistem memproses keputusan.
- Jika validasi teknis gagal, status tetap `approved_fase1` dan Admin
  memperbaiki file. Pengajuan hanya menjadi `pending_fase2` setelah
  validasi teknis berhasil.
- Penolakan dokumen awal Internal menghasilkan `rejected_fase1` dan
  mengakhiri alur pengajuan tersebut pada implementasi saat ini.
- Penolakan dokumen awal CSR menghasilkan `rejected_fase1` dan
  mengakhiri alur pengajuan. Admin tidak dapat mengedit, mengajukan ulang,
  atau melanjutkan ke BAST.
- Penolakan BAST pada Internal maupun CSR mengembalikan status ke
  `approved_fase1`; Admin hanya mengunggah ulang BAST.
- Persetujuan BAST menghasilkan status `completed`.

Aturan pemisahan kewenangan di atas berlaku pada seluruh diagram, baik
diagram end-to-end maupun diagram operasional per role. Simbol pemeriksaan
di lane Super Admin tidak boleh ditafsirkan sebagai kewenangan untuk mengubah
isi pengajuan.

## Diagram tambahan

- `csr-end-to-end.puml` adalah diagram lama dan bukan acuan resmi karena
  cabang penolakan fase pertamanya belum sesuai dengan implementasi terminal.
- `monitoring-internal-csr.puml` menjelaskan monitoring gabungan Internal
  PKS, Internal NON-PKS, dan CSR. Program Eksternal belum dimasukkan.

Menu dan route untuk memindahkan atau menghapus keseluruhan pengajuan dari
arsip tidak tersedia pada implementasi terbaru. Field `is_archived` masih
tersisa untuk kompatibilitas data lama dan tidak boleh ditafsirkan sebagai
kewenangan arsip aktif.
