# Perbandingan Spesifikasi Final Modul Kartu Piutang PUMK

Dokumen ini membandingkan spesifikasi skema PUMK final dengan workbook aktual `DatabasePUMKTJSLNewok.xlsx` dan implementasi Laravel di TJSLINKA. Nilai identitas pribadi tidak dituliskan dalam dokumen maupun log audit.

## Ringkasan keputusan final

| Area | Ekspektasi spesifikasi | Hasil aktual dan keputusan implementasi |
|---|---|---|
| Format sumber | Workbook final 89 kolom | Ada 88 kolom bisnis terisi (`A:CJ`). `CK` kosong/style-only sehingga tidak diimpor sebagai field semu. |
| Worksheet dan data | Format final tetap | Sheet `Database new versi baseon SPJ`, header baris 8, data baris 9-402 (394 record). |
| Tanggal acuan | Mengikuti workbook | Nilai `C5` adalah `2026-07-31` dan dipakai sebagai cutoff snapshot sumber. |
| Reschedule | Tiga tahap kontrak | Tersedia di `pumk_pinjaman.reschedule_ke1` sampai `reschedule_ke3`, dipetakan dari D-F. |
| Jaminan dan berkas | Data jaminan lengkap | O-X dipetakan ke field jaminan; Y-Z disiapkan sebagai path berkas tetapi kosong pada file final. |
| Kontrak dan keuangan | Ikuti susunan file final | Kontrak AA-AJ, validasi/snapshot AK-AV, saldo awal AW-AZ, transaksi bulanan BA-CJ. |
| Upsert | File revisi memperbarui baseline | `source_key` stabil membuat 394 record lama diperbarui; tidak ada record baru atau duplikat. |
| Idempotensi | File sama aman dijalankan ulang | SHA-256 disimpan unik pada `file_hash`; file identik ditolak sebelum mengubah data. |
| Nilai kosong | Jangan menghapus pelengkapan manual | Merge melewati nilai sumber kosong bila field database sudah terisi. |
| Angsuran manual | Harus tetap aman saat re-import | Rekonsiliasi hanya menghapus/mengganti transaksi sumber; transaksi buatan Admin PUMK dipertahankan dan konflik diberi warning. |
| Kalkulator | Bandingkan sebelum dipakai | AK, AL, dan AM dibandingkan pada 394 record; 0 mismatch. Nilai Excel tetap baseline historis. |
| Warning | Perlu pemeriksaan data | 222 record `needs_review` adalah warning kualitas sumber, bukan kegagalan; 394/394 berhasil. |
| Pemisahan Admin | Admin TJSL dan Admin PUMK tidak saling masuk | Guard, role, route, middleware, dan dashboard PUMK tetap terpisah dari Admin TJSL. |

## Verifikasi workbook final

| Atribut | Nilai |
|---|---|
| File | `DatabasePUMKTJSLNewok.xlsx` |
| Sheet | `Database new versi baseon SPJ` |
| Header | Baris 8 |
| Data | Baris 9-402 (394 baris) |
| Kolom bisnis | `A:CJ` (88 kolom) |
| Kolom `CK` | Kosong/style-only |
| Cutoff `C5` | 31 Juli 2026 |
| SHA-256 | `5f101e3f9d313ef91fe9f44c34e3f5174adaa1550473c113f52dbf40f51ca1fd` |
| Wilayah ternormalisasi | 17 |
| Sektor yang dipakai | 11 |

Pemetaan kelompok final:

```text
A:C    nomor, mitra, dan SPJ awal
D:F    kontrak reschedule ke-1 s.d. ke-3
G:N    identitas dan klasifikasi mitra
O:Z    jaminan serta path berkas
AA:AJ  data kontrak pinjaman
AK:AV  snapshot/validasi keuangan
AW:AZ  saldo awal s.d. Desember 2025
BA:CJ  angsuran bulanan Januari-Desember 2026
```

## Kecocokan field baru

### Reschedule

| Field | Kolom | Nilai terisi |
|---|---|---:|
| `reschedule_ke1` | D | 158 |
| `reschedule_ke2` | E | 75 |
| `reschedule_ke3` | F | 21 |

### Jaminan dan berkas

| Field | Kolom | Nilai terisi |
|---|---|---:|
| `jenis_jaminan` | O | 174 |
| `jaminan_no_pol` | P | 174 |
| `jaminan_no_bpkb` | Q | 174 |
| `jaminan_merk` | R | 173 |
| `jaminan_type` | S | 174 |
| `jaminan_tahun_kendaraan` | T | 173 |
| `jaminan_no_sertifikat` | U | 89 |
| `jaminan_luas` | V | 83 |
| `jaminan_atas_nama` | W | 174 |
| `jaminan_alamat` | X | 172 |
| `berkas_spj_path` | Y | 0 |
| `berkas_jaminan_path` | Z | 0 |

Field Y-Z dibiarkan `NULL` sampai berkas benar-benar diunggah melalui aplikasi. Nilai kosong tidak disulap menjadi path palsu.

## Perilaku importer final

### Identitas versi dan record

- `file_hash` memakai SHA-256 file untuk mencegah file yang sama diproses ulang.
- `source_key` dibentuk stabil dari nomor urut sumber dan jenis record.
- Karena seluruh 394 `source_key` cocok dengan baseline, file final menghasilkan 0 record baru dan 394 pembaruan.
- `row_hash` memberi jejak perubahan per baris tanpa menyimpan PII mentah di pesan audit.

### Perlindungan isian manual

Pada upsert, sel Excel kosong tidak menimpa field database yang sudah memiliki nilai. Dengan demikian, pelengkapan data yang dilakukan Admin PUMK melalui form tetap tersimpan walaupun workbook revisi belum mengisi field tersebut. Nilai sumber nonkosong tetap dapat memperbarui nilai impor lama.

Record manual juga dibedakan melalui audit `created_by`; importer tidak boleh mengambil alih record manual yang kebetulan berkonflik dengan key sumber.

### Rekonsiliasi angsuran

- Transaksi sumber di-upsert per `(pinjaman_id, periode)`.
- Sel bulanan kosong/nol tidak dibuat sebagai transaksi.
- Bila revisi mengosongkan bulan yang dahulu terisi, hanya angsuran hasil import lama yang dihapus.
- Angsuran manual dengan `batch_id = NULL` dan `created_by` terisi tidak ditimpa atau dihapus.
- Benturan periode dengan angsuran manual dicatat sebagai warning untuk ditinjau, bukan dipaksa overwrite.

### Record yang hilang dari sumber

Pinjaman impor yang tidak lagi ada pada file baru hanya boleh dinonaktifkan jika cakupan file valid dan tidak ada baris gagal. Pinjaman buatan manual tidak masuk proses deactivation. Pada import final ini tidak ada record yang dinonaktifkan.

## Snapshot sumber dan kalkulator

`source_updated_at` untuk data final mengikuti cutoff `2026-07-31`, bukan waktu server saat import. Ini penting supaya sistem dapat membedakan transaksi yang sudah termasuk snapshot Excel dengan angsuran manual setelah cutoff.

Nilai tunggakan, kolektibilitas, dan sisa pinjaman hasil import tetap mengikuti snapshot sumber. Kalkulator aplikasi tidak otomatis menimpa baseline historis. Angsuran manual setelah cutoff tetap diperhitungkan sebagai pengurang baseline ketika kartu piutang ditampilkan.

Perbandingan produksi dilakukan hanya pada tiga nilai yang sudah memiliki definisi sebanding:

| Kolom | Pembanding kalkulator | Hasil |
|---|---|---:|
| AK | Total pokok masuk | 0 mismatch |
| AL | Total bunga masuk | 0 mismatch |
| AM | Total angsuran masuk (pokok + bunga) | 0 mismatch |

Total 394 pinjaman diperiksa. Kolom lain di `AN:AV` belum dipakai untuk mengganti baseline karena aturan tunggakan/kolektibilitas resmi tetap harus mengikuti keputusan bisnis.

## Hasil batch produksi

File final berhasil diimpor sebagai batch terbaru ID 3.

| Metrik | Hasil |
|---|---:|
| Total baris | 394 |
| Berhasil | 394 |
| Gagal | 0 |
| Baru | 0 |
| Diperbarui | 394 |
| Dinonaktifkan | 0 |
| `needs_review` | 222 |
| Kalkulator diperiksa | 394 |
| Kalkulator berbeda (AK-AL-AM) | 0 |

`needs_review` tidak berarti importer gagal. Status tersebut menunjukkan data sumber memerlukan pemeriksaan, misalnya ada field kosong, tanggal anomali, nilai negatif, atau warning validasi lain. Tidak ada baris yang hilang akibat warning ini.

## Pemisahan UI dan kewenangan

- Admin PUMK masuk melalui guard dan area `/admin-pumk`.
- Admin TJSL tidak dapat membuka dashboard PUMK; Admin PUMK tidak dapat membuka dashboard Admin TJSL.
- Form Tambah/Edit PUMK telah menyediakan field reschedule dan jaminan dengan pola UI yang sama seperti modul Admin yang ada.
- Data identitas sensitif tetap dibatasi dan tidak dipakai pada dashboard agregat.

## Backup dan pemulihan

Sebelum migration dan import final, database telah dibackup ke:

`storage/backups/tjslinka_before_pumk_final_import_20260830_173333.sql`

SHA-256 backup:

`A775DC558C3F248E9181149B36BF2857DA829156AEB4A3CAE8F39CBA6418ACE0`

Backup ini adalah titik pemulihan sebelum perubahan skema reschedule/jaminan dan sebelum batch final diterapkan.

## Kesimpulan

Implementasi final sesuai struktur workbook aktual, bukan sekadar angka kolom pada dokumen spesifikasi. Seluruh 394 record diperbarui tanpa duplikasi, nilai kosong tidak menghapus pelengkapan manual, transaksi manual tetap aman saat re-import, dan hasil kalkulator yang sudah dapat dibandingkan cocok 100% dengan snapshot AK-AL-AM. Warning yang tersisa adalah pekerjaan kualitas data, bukan kegagalan teknis import.
