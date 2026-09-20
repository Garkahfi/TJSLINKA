# Batas Kewenangan: Admin TJSL vs Super Admin

Dokumen ini WAJIB dijadikan acuan setiap kali membangun/memperbaiki fitur, flowchart, atau dokumentasi apa pun yang menyangkut alur Admin ↔ Super Admin di sistem LENSA TJSL INKA. Kalau ada konflik antara dokumen ini dengan asumsi yang muncul saat coding (termasuk flowchart yang pernah dibuat sebelumnya), **dokumen ini yang benar** — perbaiki yang bertentangan, bukan sebaliknya.

## Prinsip Inti: Pemisahan Tugas (Separation of Duties)

**Admin TJSL** = satu-satunya pihak yang membuat, mengisi, meng-upload, dan meng-edit konten laporan/program/bantuan.  
**Super Admin** = HANYA memeriksa dan memutuskan (Approve/Reject dengan alasan). **Tidak pernah** membuat, meng-upload, atau meng-edit isi laporan.

Kalau Super Admin merasa ada yang perlu diperbaiki di laporan yang diajukan Admin, jalurnya SELALU: **Reject dengan alasan yang jelas** → Admin membaca alasan itu → Admin sendiri yang edit & ajukan ulang. Bukan Super Admin edit langsung.

## Tabel Kewenangan per Aksi

| Aksi | Admin TJSL | Super Admin |
|---|---|---|
| Isi form program/bantuan baru | ✅ | ❌ (sudah dicabut sebelumnya) |
| Upload dokumen Fase 1 (A-E, atau subset PKS/NON-PKS) | ✅ | ❌ |
| Upload dokumen F/BAST (Fase 2) | ✅ | ❌ ⚠️ **INI YANG SALAH DI FLOWCHART CODEX — PERBAIKI** |
| Edit isi laporan yang sudah diajukan (status pending/draft/rejected) | ✅ | ❌ **DIREVISI — sebelumnya diizinkan, SEKARANG DICABUT** |
| Approve / Reject Fase 1 | ❌ | ✅ |
| Approve / Reject Fase 2 (BAST) | ❌ | ✅ |
| Isi alasan penolakan | ❌ | ✅ (wajib diisi saat reject) |
| Lihat status & riwayat laporan | ✅ (punya sendiri) | ✅ (semua Admin) |

## Perbaikan yang Harus Dilakukan Sekarang

### 1. Cabut Tombol "Edit" dari Halaman Review Super Admin

Di halaman Detail/Pelihat Program (status `pending_fase1`, `pending_fase2`, `draft`, `rejected_fase1`) yang diakses Super Admin — **hapus tombol Edit sepenuhnya**. Sisakan cuma:

- Status `pending_fase1` → tombol **Approve** dan **Reject** (dengan field alasan wajib)
- Status `pending_fase2` → tombol **Approve** dan **Reject** (dengan field alasan wajib)
- Status lain (draft, rejected) → **read-only murni**, Super Admin cuma bisa lihat, tidak ada tombol aksi apa pun di halaman ini (draft itu belum diajukan, rejected sudah final di fase itu)

Field-field program (Nama Program, Deskripsi, dokumen A-F, dst) yang tadinya bisa diedit Super Admin sekarang jadi **read-only** di semua kondisi status, tanpa terkecuali.

### 2. Perbaiki Alur Upload BAST — Pastikan HANYA Admin yang Bisa

Cek route, controller, DAN Blade view untuk upload dokumen F/BAST:

- Route submit upload BAST harus di bawah middleware Admin (bukan Super Admin), dan field upload-nya cuma muncul di halaman yang diakses Admin.
- Halaman Super Admin untuk status `approved_fase1` (menunggu BAST) harus **read-only** — cuma nampilin info "menunggu Admin upload BAST", TIDAK ADA field upload di halaman itu.
- Halaman Super Admin untuk status `pending_fase2` (BAST sudah diupload Admin, menunggu review) — Super Admin cuma bisa **lihat** file BAST yang sudah ada + tombol Approve/Reject, TIDAK BISA ganti/upload ulang file itu sendiri.

### 3. Perbaiki Semua Flowchart yang Sudah Dibuat Sebelumnya

Kalau ada diagram/flowchart (dokumentasi, komentar kode, atau file terpisah) yang pernah dibuat Codex dan masih nunjukin "Super Admin upload BAST" atau "Super Admin edit laporan" — cari dan revisi semuanya supaya konsisten sama dokumen ini. Jangan biarkan dokumentasi lama yang salah tetap ada berdampingan dengan yang sudah benar, itu bikin bingung ke depannya.

## Test Manual

1. Login Super Admin, buka laporan Program TJSL berstatus `pending_fase1` — pastikan TIDAK ADA tombol Edit di halaman ini, cuma Approve/Reject.
2. Login Super Admin, buka laporan berstatus `approved_fase1` (menunggu BAST) — pastikan TIDAK ADA field upload BAST di halaman Super Admin.
3. Login Admin, buka laporan milik sendiri yang `approved_fase1` — pastikan Admin (bukan Super Admin) yang bisa upload BAST di sini.
4. Login Super Admin, buka laporan berstatus `pending_fase2` — pastikan bisa lihat file BAST yang sudah diupload Admin, tapi tidak ada tombol ganti/upload ulang.
5. Cek ulang semua flowchart/diagram dokumentasi yang ada — pastikan sudah konsisten, tidak ada lagi yang bilang Super Admin upload BAST atau edit laporan.
