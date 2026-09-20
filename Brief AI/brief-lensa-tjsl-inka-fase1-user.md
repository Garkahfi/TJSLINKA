# Brief Pengembangan — LENSA TJSL INKA
## Fase 1: Halaman Front-End dalam Konteks Admin (tanpa Auth & DB sungguhan)

---

## RINGKASAN TUGAS UNTUK CODEX

Bangun halaman-halaman front-end dari website monitoring TJSL PT INKA (Persero) bernama **LENSA TJSL INKA**, menggunakan **Laravel 13 (PHP 8.3+) + Blade + Tailwind CSS**. **Update penting:** halaman-halaman ini (Home, Teras TJSL, Program TJSL) untuk sementara dianggap sebagai bagian dari pengalaman **Admin** yang sudah login — BUKAN situs publik tanpa login. Role "User/Publik" ditunda dulu, baru ditambahkan belakangan kalau memang dibutuhkan (lihat "KEPUTUSAN KUNCI"). Semua data untuk fase ini memakai **dummy/static data** (array PHP atau JSON lokal) — belum ada database sungguhan, belum ada sistem login yang benar-benar berfungsi. Tujuannya: replikasi tampilan & struktur halaman sesuai desain Figma seakurat mungkin, dengan komponen yang reusable dan siap dihubungkan ke database asli di fase berikutnya (Dashboard Admin & Super Admin).

Project sudah dibuat dengan nama folder **`TJSLINKA`**, dan di dalamnya sudah ada folder **`Assets/`** berisi file-file dari export Figma (video, logo, foto, screenshot referensi desain). **Sebelum mulai coding, langkah pertama Codex adalah membuka dan menginventarisasi folder `Assets/` itu** — lihat Bagian 4.1 untuk daftar isinya dan mana yang aset sungguhan vs. yang cuma referensi visual.

---

## KEPUTUSAN KUNCI (SUDAH FINAL — jangan tanya ulang ke user untuk poin-poin ini)

- **[REVISI] Bukan situs publik — dianggap konteks Admin dulu.** Keputusan sebelumnya soal "akses publik tanpa login" **dibatalkan**. Sekarang: seluruh halaman (Home, Teras TJSL, Program TJSL) dianggap sebagai tampilan yang dilihat **Admin yang sudah login**, persis seperti di mockup Figma. Navbar cukup **hardcode/dummy "Hi, User Admin"** (kembali seperti desain asli), TIDAK perlu state Guest/tombol Login yang aktif secara logic. Role "User/Publik" (pengunjung tanpa login) **ditunda** — kalau nanti memang dibutuhkan, tinggal ditambahkan belakangan sebagai fase terpisah, tidak perlu dibangun sekarang.
  - Halaman `/login` (Bagian 5.1) tetap dibangun sesuai desain, tapi untuk Fase 1 dianggap sebagai "pintu masuk" konseptual saja — submit-nya belum perlu terhubung ke backend apa pun. Setelah Fase 2 (Admin & Super Admin + DB) selesai, baru `/login` ini disambungkan ke auth sungguhan dan benar-benar men-gate akses ke seluruh halaman.
- **Tidak ada mockup mobile.** Codex bertanggung jawab membangun tampilan responsive sendiri mengikuti best practice Tailwind (mobile-first: breakpoint `sm`/`md`/`lg`/`xl`), dengan desain 1440px sebagai acuan utama untuk layar `lg` ke atas. Jangan asal stack elemen tanpa mempertimbangkan hierarki visual di desain desktop.
- **4 pilar TJSL, bukan 5.** Pilar yang benar: **Sosial, Ekonomi, Lingkungan, Hukum & Tata Kelola** (4 item). File `Property 1=Variant5.png` di Figma adalah duplikat dari "Sosial" — abaikan file itu, animasi hero di komponen `hero-video` varian `animated-pillars` cukup loop 4 item saja (Default=Sosial, Variant2=Ekonomi, Variant3=Lingkungan, Variant4=Hukum & Tata Kelola).
- **Akses dokumen (Lihat/Unduh) — tidak lagi masalah governance publik.** Karena semua halaman sekarang dianggap konteks Admin (bukan publik), kekhawatiran soal dokumen sensitif ter-expose ke publik jadi tidak relevan untuk saat ini. Tombol Lihat/Unduh di tabel "Kelengkapan Dokumen Program" tetap **dibuat non-fungsional di Fase 1**, tapi alasannya murni teknis (belum ada file/data sungguhan, bukan soal keamanan) — cukup kasih placeholder link atau state disabled dengan tooltip "Dokumen belum tersedia". Pertanyaan soal governance ini otomatis relevan lagi kalau nanti role publik ditambahkan.

---

## 1. Konteks Proyek

Website ini dipakai oleh divisi **TJSL (Tanggung Jawab Sosial dan Lingkungan) PT INKA (Persero)** untuk memonitor dan mempublikasikan program-program CSR/tanggung jawab sosial perusahaan.

Rencana keseluruhan sistem punya 3 role, tapi **untuk sekarang dikerjakan sebagai 1 kesatuan (Admin) dulu**:
- **Admin** — input laporan/program, DAN melihat semua halaman front-end (Home, Teras TJSL, Program TJSL) yang sedang dibangun di brief ini (**Fase 1 — ini yang dikerjakan sekarang**, halaman-halamannya dulu, panel input/CRUD-nya menyusul di Fase 2)
- **Super Admin** — approve/reject laporan (menyusul, Fase 2, bareng Admin panel & database)
- **User/Publik** — akses tanpa login untuk masyarakat umum — **ditunda**, belum dikerjakan sama sekali. Kalau nanti dibutuhkan, tinggal ditambahkan sebagai fase terpisah (kemungkinan besar tinggal ubah state navbar dari hardcode admin jadi conditional guest/logged-in, karena struktur halamannya sudah sama).

## 2. Tech Stack

| Layer | Pilihan |
|---|---|
| Backend framework | Laravel 13.x (rilis stabil 17 Maret 2026 — pastikan PHP 8.3+ terpasang) |
| Templating | Blade |
| Styling | Tailwind CSS |
| Database | MySQL (schema baru dibuat di Fase 2 — lihat catatan di Bagian 10) |
| Chart library | Chart.js atau ApexCharts (untuk dashboard & progress gauge) |
| Asset | Video MP4 (hero background), gambar produk/program |

## 3. Prinsip Kerja Fase Ini

1. **Semua data dummy.** Simpan di array PHP dalam Controller, atau file `resources/data/*.json` yang di-load lewat helper — jangan bikin migration/model Eloquent sungguhan dulu.
2. **Struktur data dummy dirancang mirip skema DB yang akan datang** (lihat Bagian 10), supaya saat Fase 2 tiba, tinggal ganti sumber data dari array ke query Eloquent tanpa bongkar ulang Blade view.
3. **Belum ada auth aktif**, dan untuk Fase 1 itu tidak masalah — navbar cukup **hardcode "Hi, User Admin"** persis seperti desain asli, karena semua halaman ini sekarang dianggap konteks Admin (bukan situs publik). Lihat "KEPUTUSAN KUNCI" di atas. Halaman `/login` sendiri sudah jadi tampilannya tapi submit-nya belum terhubung ke backend apa pun.
4. **Fokus utama:** kesesuaian visual ke desain Figma, kelancaran routing antar halaman, dan komponen Blade yang reusable (dipakai ulang di banyak halaman, bukan copy-paste).

## 4. Struktur Folder yang Disarankan

Project root sudah bernama `TJSLINKA/` dan sudah berisi folder `Assets/` (lihat Bagian 4.1). Struktur Laravel standar yang perlu dibentuk di dalamnya:

```
TJSLINKA/
  Assets/                     <- SUDAH ADA, folder mentah hasil export Figma (lihat Bagian 4.1)
  resources/
    views/
      layouts/
        app.blade.php          <- layout utama (navbar + footer wrapper)
      components/
        navbar.blade.php
        footer.blade.php
        hero-video.blade.php   <- hero dengan video bg (2 varian: simple & animated-pillars)
        program-card.blade.php
        product-card.blade.php
        faq-accordion.blade.php
        document-table-row.blade.php
        progress-gauge.blade.php
        pillar-badge.blade.php
      pages/
        login.blade.php
        home.blade.php
        teras-tjsl.blade.php
        program-overview.blade.php
        program-rincian.blade.php
        program-detail.blade.php
    data/                       <- dummy data JSON (sementara, sebelum ada DB)
      programs.json
      products.json
      news.json
      faqs.json
  routes/
    web.php
  public/
    videos/
      waterfall-bg.mp4          <- COPY dari Assets/7848_Mauritius_Water_1920x1080.mp4
      people-talking-bg.mp4     <- COPY dari Assets/People_Leisure_3840x2160.mp4
    images/
      logo/
        lensa-tjsl-inka.png     <- COPY dari Assets/LENSA TJSL INKA.png
      programs/
        sample-1.jpg            <- COPY dari Assets/Frame 244.png (dipakai sbg foto dummy program)
```

⚠️ **Penting soal path:** Laravel cuma bisa serve file statis (video, gambar) yang ada di dalam folder `public/`. Folder `Assets/` yang sudah ada di root project itu **bukan** tempat yang bisa langsung diakses browser. Instruksikan Codex untuk **meng-copy** (bukan memindahkan, biar file asli tetap ada sebagai arsip) file-file yang dipakai sungguhan ke `public/videos/` dan `public/images/...` sesuai kebutuhan komponen, lalu referensikan pakai helper `asset('videos/waterfall-bg.mp4')` dsb di Blade — jangan hardcode path `Assets/...` di kode.

### 4.1 Katalog Isi Folder `Assets/` — Mana yang Aset Sungguhan, Mana yang Cuma Referensi

Ini penting supaya Codex tidak salah pakai — sebagian file di `Assets/` itu **screenshot hasil export Figma untuk referensi visual saja**, bukan gambar yang dipasang di situs.

| File | Jenis | Perlakuan |
|---|---|---|
| `7848_Mauritius_Water_1920x1080.mp4` | Video asli | ✅ Dipakai sungguhan — copy ke `public/videos/`, background hero di Login & Home |
| `People_Leisure_3840x2160.mp4` | Video asli | ✅ Dipakai sungguhan — copy ke `public/videos/`, background hero animasi pilar di Program TJSL (Overview & Rincian) |
| `LENSA TJSL INKA.png` | Logo asli | ✅ Dipakai sungguhan — copy ke `public/images/logo/`, dipasang di navbar, hero, & login |
| `Frame 244.png` | Foto dokumentasi asli | ✅ Bisa dipakai sebagai foto dummy/placeholder di kartu program (grid Rincian Program), sampai foto asli tiap program tersedia |
| `Login.png` | Screenshot referensi desain | ❌ JANGAN dipasang sebagai gambar di situs — ini cuma acuan visual buat Codex membangun halaman `/login` dari HTML/CSS asli |
| `home.png` | Screenshot referensi desain | ❌ Sama — acuan visual untuk membangun halaman Home |
| `katalog.png` | Screenshot referensi desain | ❌ Sama — acuan visual untuk membangun halaman Teras TJSL |
| `overview program.png` | Screenshot referensi desain | ❌ Sama — acuan visual untuk membangun halaman Overview Program |
| `rincian program.png` & `rincian program2.png` | Screenshot referensi desain | ❌ Sama — acuan visual untuk membangun halaman Rincian Program & Detail |
| `Property 1=Default/Variant2/3/4/5.png` | Preview frame animasi | ❌ JANGAN dipasang sebagai gambar — ini potongan preview dari animasi teks pilar di atas video hero. Teks & transisinya perlu dibangun ulang sebagai HTML/CSS/JS animasi (lihat Bagian 5.4), bukan ditampilkan sebagai gambar statis |

Gambar produk UMKM (grid di Teras TJSL), foto galeri tiap program, dan foto berita — **belum ada aset aslinya**. Pakai placeholder/dummy image dulu (misal dari `https://placehold.co/` atau warna solid) sampai user menyediakan foto sungguhan.

## 5. Breakdown Halaman & Routing

### 5.1 Login — `/login`
- **Belum fungsional.** UI dibuat sesuai desain, tapi submit form sementara nonaktif atau tampilkan pesan "Fitur login akan tersedia di fase berikutnya".
- Background: video air terjun (`waterfall-bg.mp4`), full-screen, loop, dengan overlay putih semi-transparan (efek blur/frosted terlihat di desain).
- Card form di tengah: logo LENSA TJSL INKA, input Username, input Password, tombol Login.

### 5.2 Home — `/`
- **Navbar** (component reusable): logo, menu Home / Teras TJSL / Program TJSL (dropdown: "Overview Program TJSL INKA", "Rincian Program TJSL INKA"), badge **"Hi, User Admin"** (hardcode/dummy — lihat "KEPUTUSAN KUNCI"), icon language switcher.
- **Hero section** (component `hero-video`, varian simple): video air terjun background, logo besar di tengah, teks deskripsi TJSL INKA.
- **Section "Dashboard Program TJSL"**: kumpulan chart (donut progres, bar per pilar, bar per TPB, peta wilayah, tabel realisasi anggaran per bidang/program) — dummy data, styling meniru screenshot referensi.
- **Section "Dashboard Program PUMK"**: chart donut (sektor ekonomi, kualitas program), line chart, bar chart — dummy data.
- **Section FAQ**: accordion component. Data dummy dulu, tapi struktur di-loop dari array (bukan hardcode per item) — user sudah minta ini bisa diedit admin nanti, jadi component-nya harus generic.
- **Footer** (component reusable): Resources links, Contact info, logo INKA.

### 5.3 Teras TJSL (Katalog) — `/teras-tjsl`
- Hero kecil (reuse `hero-video`, judul "Teras TJSL").
- Teks deskripsi (dummy paragraf, ganti nanti).
- Grid 3 kartu paket produk (Paket A/B/C, tiap kartu: gambar, nama, harga, daftar isi) + 1 kartu highlight Paket D (custom, dengan info kontak pemesanan via IG/WA/link eksternal).
- Grid produk individual UMKM (nama produk + nama UMKM asal + gambar), jumlah item banyak — render dari array dummy, siapkan pagination atau lazy-load karena grid-nya panjang di desain asli.

### 5.4 Program TJSL — Overview — `/program-tjsl/overview`
- Hero (component `hero-video`, varian **animated-pillars**): video orang ngobrol sebagai background, dengan **teks overlay transparan yang animasi berganti-ganti** menampilkan judul + deskripsi **4 pilar** (Sosial/Ekonomi/Lingkungan/Hukum & Tata Kelola — sudah final, lihat "KEPUTUSAN KUNCI"). Ini butuh sedikit JS (interval + fade transition) di atas video HTML5.
- Filter tahun (2024/2025/2026) sebagai toggle button.
- Tabel "Kelengkapan Dokumen" dikelompokkan per pilar (warna header sesuai pilar: biru/oranye/hijau/merah), tiap baris punya 6 kolom checklist (A–F, dummy icon check/silang, warna pink=Dokumen Mandatory, biru=Dokumen Pelengkap).
- **Klik baris program → redirect ke halaman Rincian Program Detail** (`/program-tjsl/rincian/{slug}`), bukan expand di tempat (sudah dikonfirmasi user).
- Section "Berita TJSL INKA": card berita (gambar, judul, sumber, tanggal) — dummy data, grid 4 kolom.
- Footer.

### 5.5 Program TJSL — Rincian — `/program-tjsl/rincian`
- Hero sama seperti Overview (reuse component yang sama).
- Tab filter pilar (Sosial/Ekonomi/Lingkungan/Hukum & Tata Kelola) dengan indikator warna bulat.
- Grid kartu program (component `program-card`): gambar + judul, border-top warna sesuai pilar aktif.
- Pagination di bawah grid.
- Klik kartu → `/program-tjsl/rincian/{slug}`.
- Footer.

### 5.6 Rincian Program — Detail — `/program-tjsl/rincian/{slug}`
- Hero dengan foto/video dokumentasi program + judul program spesifik.
- Galeri 4 foto kecil di bawah hero.
- Paragraf deskripsi program.
- Info box tab/list: **Sasaran / Lokasi / Mitra** (icon + label).
- Widget **progress gauge** (component `progress-gauge`, donut setengah lingkaran) menampilkan persentase penyerapan anggaran — dummy data.
- Section "Tujuan Program": 3 blok (foto kiri + teks kanan).
- Tabel "Kelengkapan Dokumen Program" (component `document-table-row`): kolom Checklist / Lihat / Unduh — untuk Fase 1, tombol Lihat & Unduh diberi state disabled/placeholder karena belum ada file dokumen sungguhan (bukan lagi soal governance publik, lihat "KEPUTUSAN KUNCI").
- Footer.

## 6. Komponen Reusable — Prioritas Bikin Duluan

1. `layouts/app.blade.php` — wrapper navbar+footer+slot content
2. `components/navbar.blade.php`
3. `components/footer.blade.php`
4. `components/hero-video.blade.php` — props: `video`, `variant` (`simple` | `animated-pillars`), `title`, `description`
5. `components/pillar-badge.blade.php` — badge warna per pilar (dipakai di banyak tempat: tab filter, header tabel, border kartu)
6. `components/program-card.blade.php`
7. `components/product-card.blade.php`
8. `components/faq-accordion.blade.php`
9. `components/document-table-row.blade.php`
10. `components/progress-gauge.blade.php`

## 7. Design Tokens

⚠️ **Wajib baca bersamaan dengan dokumen terpisah `design-tokens-lensa-tjsl-inka.md`** — isinya kode hex warna hasil ekstraksi langsung dari file desain (bukan tebakan), termasuk config Tailwind siap-pakai. Jangan styling pakai warna hasil tebakan lagi.

- Font heading: sans-serif tebal (kemungkinan Poppins/Inter/sejenis — masih perlu verifikasi manual dari Figma, lihat Bagian 6 di dokumen design tokens).
- Breakpoint desain: 1440px (desktop-first). **Tidak ada mockup mobile** — responsive dibangun mandiri oleh Codex mengikuti best practice Tailwind (lihat "KEPUTUSAN KUNCI").

## 8. Eksplisit TIDAK Dikerjakan di Fase Ini

- Login/auth yang benar-benar berfungsi
- Panel Admin & Super Admin
- Database & migration sungguhan
- Alur approval laporan
- Dashboard live embed dari tools eksternal (baru dipertimbangkan di Fase 2 jika diputuskan)

## 9. Pertanyaan Terbuka — Konfirmasi ke User Sebelum/Selama Development

- Nama font & kode warna resmi (idealnya export style guide dari Figma langsung, bukan estimasi visual). Sementara pakai estimasi di Bagian 7.

## 10. Sketsa Struktur Data Dummy (referensi untuk Fase 2 — TIDAK diimplementasikan sebagai DB sekarang)

Ini hanya PANDUAN bentuk array/JSON dummy supaya field-nya sudah mirip skema tabel yang akan dibuat nanti. Jangan buat migration dari ini — cukup dipakai sebagai bentuk data di `resources/data/*.json`.

```json
// programs.json (dummy)
[
  {
    "id": 1,
    "slug": "program-jaminan-sosial-pekerja-rentan",
    "pillar": "sosial",
    "title": "Program Jaminan Sosial Bidang Ketenagakerjaan bagi Pekerja Rentan",
    "cover_image": "programs/jaminan-sosial.jpg",
    "description": "...",
    "sasaran": "...",
    "lokasi": "...",
    "mitra": "...",
    "budget_planned": 50000000,
    "budget_realized": 0,
    "documents": [
      { "code": "A", "name": "Dokumen BAPB A", "checked": true, "file_url": null },
      { "code": "B", "name": "Dokumen BAPB B", "checked": true, "file_url": null }
    ],
    "gallery": ["programs/gallery-1.jpg", "programs/gallery-2.jpg"]
  }
]
```

```json
// faqs.json (dummy) — disiapkan generic karena akan editable admin di Fase 2
[
  { "id": 1, "question": "Apa Itu Lensa TJSL INKA ?", "answer": "..." }
]
```

---

**Cara pakai dokumen ini:** salin isi dokumen ini ke Codex sebagai instruksi awal, lalu lanjutkan dengan detail tambahan (misal warna/font pasti dari Figma, atau file gambar referensi) begitu tersedia.
