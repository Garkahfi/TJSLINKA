# Brief Pengembangan — LENSA TJSL INKA
## Fase 2: Dashboard Admin & Skema Database

---

## RINGKASAN TUGAS UNTUK CODEX

Bangun **Dashboard Admin** (Super Admin menyusul di Fase 3) untuk website LENSA TJSL INKA, plus **skema database MySQL sungguhan** menggantikan dummy data JSON dari Fase 1. Dashboard ini punya 2 modul utama yang independen satu sama lain: **Program TJSL** dan **Bantuan CSR**, masing-masing dengan alur List → Buat → Detail/Review (tergantung status) → Arsip.

---

## KEPUTUSAN KUNCI (SUDAH FINAL)

- **Program TJSL dan Bantuan CSR adalah 2 modul terpisah**, dengan tabel database terpisah pula (bukan satu tabel "submissions" gabungan) — field-nya cukup berbeda: Program TJSL punya kategori pilar & dokumen legal/kerja sama, Bantuan CSR punya struktur "Rincian" berulang (rincian kegiatan, penerima, jenis, quality, nominal) yang tidak ada di Program TJSL.
- **Bantuan CSR TIDAK punya kategori pilar** (Sosial/Ekonomi/Lingkungan/Hukum & Tata Kelola) — itu cuma berlaku untuk Program TJSL.
- **Status siklus hidup** untuk kedua modul: `draft` → `pending` → `approved` **atau** `rejected` (kalau rejected, admin bisa edit & ajukan ulang jadi `pending` lagi). Field `is_archived` terpisah dari status — item arsip tetap punya status historisnya, cuma nggak muncul di list aktif.
- **Login admin pakai background foto kereta** (`Kereta.jpg`), bukan video air terjun seperti login publik — beda tema untuk membedakan sisi internal vs publik.
- **Halaman "Pelihat/Detail"** satu komponen reusable per modul, tapi tampilannya kondisional berdasarkan status:
  - `draft` / `rejected` → semua field editable, ada tombol Lihat/Ganti/Hapus per dokumen, tombol "Simpan Draft" & "Simpan dan Ajukan Kepada Super Admin"
  - `pending` → read-only, tombol "Batal Ajukan Kepada Super Admin" & "Kembali ke Overview"
  - `approved` / arsip → full read-only, tanpa tombol aksi (kecuali mungkin "Lihat" untuk foto)

---

## 0. Design Tokens

⚠️ **Wajib baca bersamaan dengan dokumen terpisah `design-tokens-lensa-tjsl-inka.md`** sebelum mulai styling dashboard Admin — dokumen itu berisi kode hex warna hasil ekstraksi langsung dari file desain (termasuk warna khusus tombol Kategori Program yang beda dari warna badge pilar), plus config Tailwind siap-pakai. Ini bagian yang paling sering meleset kalau cuma dikira-kira — pastikan dibaca dulu.

## 0.1 Fitur Ganti Password (sudah ditambahkan user di luar brief awal)

User sudah menambahkan fitur **ganti password** di halaman Profil Admin (`/admin/profil`, tombol "Ubah Password" — lihat Bagian 2.12). Ini sudah konsisten dengan skema `users.password` di Bagian 3.1, jadi tidak perlu tabel baru. Catatan implementasi:
- Password di-hash pakai `Hash::make()` bawaan Laravel (bcrypt), jangan disimpan plain text.
- Form ganti password idealnya minta: password lama (verifikasi), password baru, konfirmasi password baru.
- **Status saat ini:** akun admin masih pakai username & password sementara/manual (belum ada alur registrasi resmi). Untuk ini, buat **database seeder** (`UserSeeder`) yang insert satu akun admin awal dengan kredensial sementara — supaya nggak perlu insert manual lewat DB tiap kali fresh install. Kredensial sementara ini nanti tinggal diganti user sendiri lewat fitur Ubah Password begitu sistem live.

## 1. Struktur Navigasi Sidebar Admin

```
Home
Overview Program TJSL ▾
  ├─ Status Program        (route: /admin/program-tjsl)
  ├─ Buat Program Baru     (route: /admin/program-tjsl/create)
  └─ Arsip Program TJSL    (route: /admin/program-tjsl/arsip)
Overview Bantuan CSR ▾
  ├─ Status Bantuan CSR    (route: /admin/bantuan-csr)
  ├─ Buat Bantuan CSR      (route: /admin/bantuan-csr/create)
  └─ Arsip Bantuan         (route: /admin/bantuan-csr/arsip)
```

Ditambah halaman lepas (bukan bagian sidebar submenu): Notifikasi (`/admin/notifikasi`), Profil (`/admin/profil`), Detail/Pelihat per item (`/admin/program-tjsl/{id}` dan `/admin/bantuan-csr/{id}`).

## 2. Breakdown Halaman

### 2.1 Login Admin — `/admin/login`
Background foto `Kereta.jpg`, card form di tengah (logo INKA + "Admin Dashboard" + Username + Password + tombol Login). Fase ini: **auth SUNGGUHAN mulai aktif** (beda dari Fase 1 yang masih dummy) — pakai Laravel `Auth` bawaan, tabel `users` dengan kolom `role`.

### 2.2 Home Dashboard — `/admin/home`
Kartu ringkasan jumlah program per pilar (Sosial/Ekonomi/Lingkungan/Hukum & Tata Kelola), lalu daftar pintas Program TJSL & Bantuan CSR terbaru.

### 2.3 Status Program — `/admin/program-tjsl`
Tabel/list semua program milik admin yang login, tiap baris: warna bar = warna pilar, badge status (Draft/Waiting/Rejected/Approved) di kanan. Klik baris → ke halaman detail (`/admin/program-tjsl/{id}`).

### 2.4 Buat Program Baru — `/admin/program-tjsl/create`
Form lengkap: Nama Program, Deskripsi, Sasaran, Lokasi, Mitra, Rencana & Realisasi Anggaran, Tujuan Program, lalu upload dokumen (Surat Penawaran dan Balasan, Bukti Penjajakan Pihak Ketiga, Kajian Kelayakan Kerja Sama, Kajian Mitigasi Risiko, Perjanjian Kerja Sama, BAST, + "Tambah Dokumen" untuk dokumen custom lainnya), pilih Kategori Pilar (radio/button: Sosial/Ekonomi/Lingkungan/Hukum & Tata Kelola), upload Dokumentasi Program (+ "Tambah Foto Dokumentasi" untuk lebih dari satu foto). Tombol akhir: **Simpan Draft** (status=draft) atau **Simpan dan Ajukan Kepada Super Admin** (status=pending).

⚠️ Catatan: field "Tujuan Program" di form ini cuma satu textarea (poin-poin digabung jadi satu teks), padahal di halaman publik "Tujuan Program" tampil sebagai 3 blok foto+teks terpisah. Perlu diklarifikasi ke user apakah field ini perlu diubah jadi repeatable (foto+teks per poin), atau publik-side yang menyesuaikan render dari satu teks panjang.

### 2.5 Detail/Pelihat Program — `/admin/program-tjsl/{id}`
Satu komponen, tampilan kondisional sesuai status (lihat "KEPUTUSAN KUNCI").

### 2.6 Arsip Program TJSL — `/admin/program-tjsl/arsip`
List mirip Status Program tapi tanpa badge status (semua item sudah final), warna bar tetap pakai warna pilar. Klik baris → detail read-only.

### 2.7 Status Bantuan CSR — `/admin/bantuan-csr`
List mirip Status Program tapi TANPA warna bar pilar (polos/border hitam), badge status tetap ada.

### 2.8 Buat Bantuan CSR — `/admin/bantuan-csr/create`
Form: Nama Program Bantuan, Nama Program (relasi ke Program TJSL? — perlu klarifikasi, lihat Bagian 5), Deskripsi Bantuan, Rencana & Realisasi Anggaran, upload dokumen (Proposal Permintaan Bantuan, Formulir Kajian Proposal, Formulir Survei Calon Penerima, Laporan Hasil Survei, Formulir Persetujuan Bantuan, Dokumentasi Survei), Target Tujuan (repeatable via "Tambah Target Tujuan"), lalu section **"Rincian 1"** yang repeatable (via "Tambah Rincian Lainnya"): Rincian Kegiatan, Penerima Bantuan, Jenis Bantuan, Quality, Nominal Bantuan — tiap rincian juga punya slot foto dokumentasi sendiri. Tombol akhir sama seperti Program TJSL (Simpan Draft / Simpan dan Ajukan).

### 2.9 Detail/Pelihat Bantuan CSR — `/admin/bantuan-csr/{id}`
Sama pola kondisional seperti Program TJSL.

### 2.10 Arsip Bantuan — `/admin/bantuan-csr/arsip`
List arsip, polos tanpa warna pilar, tanpa badge status.

### 2.11 Notifikasi — `/admin/notifikasi`
List notifikasi: "Pengajuan Program TJSL Anda Ditolak/Diterima oleh Super Admin", "Pengajuan Bantuan CSR Anda Ditolak/Diterima oleh Super Admin" — trigger otomatis tiap kali Super Admin ubah status jadi approved/rejected (fitur Super Admin baru dibangun di Fase 3, tapi tabel notifikasi disiapkan sekarang).

### 2.12 Profil — `/admin/profil`
Nama Depan, Nama Belakang, Email, No Telephone, Jabatan, Alamat (mode Edit toggle), plus ganti password, dan tombol Logout.

## 3. Skema Database (MySQL)

### 3.1 `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nama_depan | varchar | |
| nama_belakang | varchar | |
| email | varchar unique | |
| password | varchar (hashed) | |
| no_telephone | varchar | |
| jabatan | varchar | |
| alamat | text | |
| avatar_path | varchar nullable | |
| role | enum('admin','super_admin') | |
| created_at, updated_at | timestamp | |

### 3.2 `pillars` (lookup, 4 baris fixed)
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| name | varchar (Sosial/Ekonomi/Lingkungan/Hukum & Tata Kelola) |
| color_hex | varchar |
| slug | varchar |

### 3.3 `programs` (Program TJSL)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| slug | varchar unique | untuk URL publik |
| pillar_id | FK → pillars | |
| nama_program | varchar | |
| deskripsi_program | text | |
| sasaran_program | text | |
| lokasi_program | text | |
| mitra_program | text | |
| rencana_anggaran | decimal | |
| realisasi_anggaran | decimal | |
| tujuan_program | text | ⚠️ lihat catatan Bagian 2.4 soal repeatable |
| status | enum('draft','pending','approved','rejected') | |
| is_archived | boolean default false | |
| rejected_reason | text nullable | |
| created_by | FK → users | |
| reviewed_by | FK → users nullable | diisi Super Admin (Fase 3) |
| submitted_at | timestamp nullable | |
| reviewed_at | timestamp nullable | |
| created_at, updated_at | timestamp | |

### 3.4 `program_documents`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| program_id | FK → programs | |
| document_type | enum('surat_penawaran_balasan','bukti_penjajakan','kajian_kelayakan','kajian_mitigasi_risiko','perjanjian_kerja_sama','bast','lainnya') | |
| nama_dokumen | varchar | |
| file_path | varchar | |
| uploaded_at | timestamp | |

### 3.5 `program_photos` (Dokumentasi Program)
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| program_id | FK → programs |
| file_path | varchar |
| is_cover | boolean |
| order | int |

### 3.6 `bantuan_csr`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nama_program_bantuan | varchar | |
| program_id | FK → programs, nullable | ⚠️ lihat catatan Bagian 5 — apakah Bantuan CSR terkait ke Program TJSL tertentu |
| deskripsi_bantuan | text | |
| rencana_anggaran | decimal | |
| realisasi_anggaran | decimal | |
| status | enum('draft','pending','approved','rejected') | |
| is_archived | boolean default false | |
| rejected_reason | text nullable | |
| created_by | FK → users | |
| reviewed_by | FK → users nullable | |
| submitted_at | timestamp nullable | |
| reviewed_at | timestamp nullable | |
| created_at, updated_at | timestamp | |

### 3.7 `bantuan_csr_documents`
Sama pola dengan `program_documents`, `document_type` enum('proposal_permintaan','formulir_kajian_proposal','formulir_survei','laporan_hasil_survei','formulir_persetujuan','dokumentasi_survei','lainnya').

### 3.8 `bantuan_csr_targets` (Target Tujuan, repeatable)
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| bantuan_csr_id | FK |
| target_text | text |
| order | int |

### 3.9 `bantuan_csr_details` (Rincian 1, 2, 3..., repeatable)
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| bantuan_csr_id | FK |
| rincian_kegiatan | text |
| penerima_bantuan | varchar |
| jenis_bantuan | varchar |
| quality | varchar |
| nominal_bantuan | decimal |
| order | int |

### 3.10 `bantuan_csr_detail_photos`
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| bantuan_csr_detail_id | FK → bantuan_csr_details |
| file_path | varchar |

### 3.11 `notifications`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users | penerima notifikasi |
| title | varchar | |
| message | text | |
| related_type | enum('program','bantuan_csr') | |
| related_id | bigint | |
| is_read | boolean default false | |
| created_at | timestamp | |

### 3.12 `status_logs` (audit trail approval, dipakai lintas modul)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| related_type | enum('program','bantuan_csr') | |
| related_id | bigint | |
| from_status | varchar | |
| to_status | varchar | |
| changed_by | FK → users | |
| note | text nullable | |
| created_at | timestamp | |

## 4. Migration Order (urutan pembuatan tabel di Laravel)
1. `users`
2. `pillars` (+ seeder 4 baris)
3. `programs`
4. `program_documents`, `program_photos`
5. `bantuan_csr`
6. `bantuan_csr_documents`, `bantuan_csr_targets`, `bantuan_csr_details`, `bantuan_csr_detail_photos`
7. `notifications`
8. `status_logs`

## 5. Pertanyaan Terbuka — Perlu Dikonfirmasi ke User

- **Relasi Bantuan CSR ↔ Program TJSL**: apakah tiap entri Bantuan CSR itu WAJIB terkait ke satu Program TJSL tertentu (form "Buat Bantuan CSR" ada field "Nama Program" terpisah dari "Nama Program Bantuan" — kemungkinan ini pilih Program TJSL yang sudah ada), atau Bantuan CSR berdiri sendiri? Ini menentukan apakah `program_id` di tabel `bantuan_csr` wajib diisi atau nullable/dihapus.
- **Field "Tujuan Program"**: perlu diubah jadi repeatable (foto+teks per poin, 3 blok) sesuai tampilan publik, atau tetap satu teks panjang dan publik-side yang parsing?
- **Super Admin (Fase 3)**: dashboard approve/reject belum ada di aset ini — akan menyusul kapan?

---

**Cara pakai dokumen ini:** lanjutan dari brief Fase 1. Tempel ke Codex setelah Fase 1 selesai, atau gabung sekaligus kalau mau develop paralel.
