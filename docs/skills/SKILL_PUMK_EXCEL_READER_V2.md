---
name: excel-source-verifier-v2
description: >
  Skill untuk membaca, membandingkan, memvalidasi, atau menyiapkan import
  database dari workbook Excel yang dapat berubah antarversi, mempunyai
  multi-row header, formula/cached value, blank vs zero, Excel date serial,
  style-only cells, serta business semantics existing yang harus dijaga.
---

# Excel Source Verifier V2

## Prinsip utama

> Workbook aktual adalah source of truth untuk struktur Excel.

> Existing backend/business rules adalah source of truth untuk semantic aplikasi.

Jangan memaksa workbook baru mengikuti mapping lama dan jangan mengubah business
semantics hanya karena posisi kolom Excel berubah.

## Workflow wajib

```text
DISCOVER -> INSPECT -> PROFILE -> COMPARE -> MAP
-> VALIDATE BUSINESS SEMANTICS -> DRY-RUN -> WRITE -> RECONCILE
```

## Pemeriksaan sumber

1. Hitung SHA-256. Jika hash berubah, lakukan inspeksi ulang.
2. List semua sheet dan pilih berdasarkan nama persis serta instruksi proyek.
3. Jangan menyimpan perubahan ke workbook sumber asli.
4. Jangan percaya `max_row`/`max_column` secara buta karena style-only cells,
   merged cells, dan formatting artifacts.
5. Inspeksi 15-20 baris awal untuk menemukan title, cutoff, group header,
   column header, dan data pertama.
6. Untuk header berulang, bentuk key dari parent/group dan subheader, misalnya
   `JANUARI.Pokok`, bukan hanya `Pokok`.

## Formula dan cached value

Baca workbook dalam dua mode:

```python
wb_formula = load_workbook(path, data_only=False)
wb_value = load_workbook(path, data_only=True)
```

Formula dipakai untuk audit dan cached value sebagai nilai sumber. Jika cached
value tidak tersedia, tandai `needs_review`; jangan membuat evaluator formula
sendiri secara spekulatif. Formula Excel juga bukan otomatis business rule
backend.

## Blank, angka, tanggal, dan persentase

- Bedakan `None`, string kosong, `0`, `0.0`, dan `-`; jangan memakai truthiness
  global.
- Kenali tanggal bertipe datetime, Excel serial, dan teks berdasarkan value,
  number format, serta konteks header.
- Tanggal acuan dapat berarti snapshot cutoff/periode laporan, bukan waktu import.
- Verifikasi raw value dan number format untuk persentase.

## Profil dan structural diff

Buat profil row count, business column count, formula/numeric/text/date/blank/zero
count. Untuk field kritis, ukur non-empty, unique, duplicate, min/max. Inspeksi
sample awal, tengah, akhir, dan random bila file besar.

Bandingkan workbook baru dengan mapping lama untuk added/removed/shifted column,
renamed header, group baru, perubahan tipe, dan perilaku formula. Gunakan
normalized header jika aman dan posisi cell sebagai guardrail.

## Source key dan business semantics

Jangan mengganti strategi source key existing secara otomatis. Gunakan:

```text
existing source key + sanity check source profile
```

Jika key cocok tetapi profil material berbeda, tandai conflict. Setelah mapping
benar, periksa Model, migration, service, calculator, importer, export, dan UI.
Bedakan baseline source, snapshot, saldo awal, dan transaction history.

## Provenance dan keamanan

- Field/baris manual tidak boleh tertimpa hanya karena sumber baru kosong.
- Jika provenance tidak jelas, tandai `needs_review`.
- Jangan mencetak KTP, telepon, rekening, alamat lengkap, atau nama pemilik ke
  log biasa. Gunakan source row, source key aman, nama field, dan error code.
- Audit minimal menyimpan file hash, sheet, source row, row hash, source key,
  dan batch id.
- Jangan menebak cell, tanggal, formula result, identitas, blank=zero, atau
  snapshot=transaction.

## Preflight dan dry-run

Preflight minimal mencakup filename/hash, daftar dan target sheet, header/data
range, group headers, formula profile, duplicate key, blank/zero profile,
structural diff, baseline/transaction/manual/import ownership, cutoff semantics,
conflict, missing cached values, serta schema delta.

Dry-run wajib menghitung matched, insert, update, unchanged, conflict, failed,
candidate deactivate, dan manual collision tanpa PII.

## Reconciliation dan regression

Setelah import, lakukan rekonsiliasi source ke DB dan DB ke source, aggregate
check, sample row check, serta regression service, UI, dan export. Bila ada satu
service pusat untuk perhitungan, seluruh output harus tetap konsisten.

## Baseline PUMK Internal V2

```text
file               : Database PUMK TJSL New (2).xlsx
sha256             : 14c466ca76ce62596052e3614fc6b7b4176c31b07c1407e9adb1b29cc97220b4
sheet              : Database new versi baseon SPJ
header utama       : row 8
group header bulan : row 7
data               : row 9-402
source rows        : 394
business columns   : A-CK
business count     : 89
C5                 : 31 Juli 2026
G8                 : Kontrak Rescheduling Ke-4
CK8                : Total Angs
formula aktual     : 11.134
cached value kosong: F192
saldo awal         : AX-BA
bulanan            : BB-CK
```

Jika hash berbeda, lakukan inspeksi ulang. Angka formula di atas merupakan hasil
inspeksi XML workbook aktual; angka perkiraan lama tidak digunakan.

## Guardrail domain

```text
PUMK INTERNAL != PUMK BRI
```

PUMK Internal menggunakan `pumk_mitra`, `pumk_pinjaman`, `pumk_saldo_awal`, dan
`pumk_angsuran`. PUMK BRI menggunakan `pumk_bri_*`. Jangan silang domain.

## Definition of done

```text
[ ] hash dan target sheet terverifikasi
[ ] multi-row header dan business range dipahami
[ ] formula/cached value serta blank/zero dipisahkan
[ ] tanggal/cutoff semantics dipahami
[ ] source key dan provenance dipertahankan
[ ] structural diff dan schema delta diterapkan
[ ] dry-run selesai
[ ] backup tersedia sebelum write
[ ] post-import reconciliation selesai
[ ] service/UI/export regression selesai
```

> Read the workbook, not the assumption.

> Read the backend, not only the workbook.

> Dry-run before write; reconcile and regression-test after write.
