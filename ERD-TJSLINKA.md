# ERD TJSLINKA (skema aplikasi lokal)

ERD ini disusun dari **53 tabel dan 61 foreign key yang benar-benar ada** pada skema MySQL yang sedang dipakai aplikasi lokal, diperiksa pada 5 Oktober 2026. Seluruh 54 migrasi terdaftar berstatus `Ran`. Pemeriksaan hanya membaca metadata skema; tidak mengubah data.

Diagram dipisah per modul agar dapat dibaca. Kolom dalam kotak adalah **kolom kunci dan contoh atribut penting**, bukan daftar seluruh kolom. Garis utuh hanya menyatakan foreign key fisik di database. `o|` di sisi tabel induk berarti foreign key pada anak boleh `NULL`; `||` berarti wajib. `o{` berarti satu induk dapat mempunyai nol atau banyak baris anak. Relasi aktor ke `users` dirinci terpisah setelah diagram supaya garis tidak menutupi relasi bisnis.

## 1. Program TJSL dan Bantuan TJSL

```mermaid
erDiagram
    pillars {
        bigint id PK
        varchar name
        varchar slug UK
    }
    programs {
        bigint id PK
        bigint pillar_id FK
        bigint created_by FK
        varchar slug UK
        enum jenis_kerjasama
        varchar status
    }
    program_documents {
        bigint id PK
        bigint program_id FK
        varchar document_type
        varchar file_path
    }
    program_photos {
        bigint id PK
        bigint program_id FK
        varchar file_path
        tinyint is_cover
    }
    program_tujuan {
        bigint id PK
        bigint program_id FK
        text deskripsi
        varchar foto_path
    }
    bantuan_csr {
        bigint id PK
        bigint program_id FK
        bigint pillar_id FK
        bigint created_by FK
        varchar nama_program_bantuan
        varchar status
    }
    bantuan_csr_details {
        bigint id PK
        bigint bantuan_csr_id FK
        text rincian_kegiatan
        decimal nominal_bantuan
    }
    bantuan_csr_detail_photos {
        bigint id PK
        bigint bantuan_csr_detail_id FK
        varchar file_path
    }
    bantuan_csr_documents {
        bigint id PK
        bigint bantuan_csr_id FK
        varchar document_type
        varchar file_path
    }
    bantuan_csr_photos {
        bigint id PK
        bigint bantuan_csr_id FK
        varchar file_path
    }
    bantuan_csr_targets {
        bigint id PK
        bigint bantuan_csr_id FK
        text target_text
    }
    program_eksternal {
        bigint id PK
        bigint pillar_id FK
        bigint created_by FK
        varchar status
        varchar no_reg
    }

    pillars ||--o{ programs : pillar_id
    pillars o|--o{ bantuan_csr : pillar_id
    pillars o|--o{ program_eksternal : pillar_id
    programs o|--o{ bantuan_csr : program_id
    programs ||--o{ program_documents : program_id
    programs ||--o{ program_photos : program_id
    programs ||--o{ program_tujuan : program_id
    bantuan_csr ||--o{ bantuan_csr_details : bantuan_csr_id
    bantuan_csr_details ||--o{ bantuan_csr_detail_photos : bantuan_csr_detail_id
    bantuan_csr ||--o{ bantuan_csr_documents : bantuan_csr_id
    bantuan_csr ||--o{ bantuan_csr_photos : bantuan_csr_id
    bantuan_csr ||--o{ bantuan_csr_targets : bantuan_csr_id
```

`bantuan_csr` adalah tabel tersendiri. `program_id` di dalamnya memang foreign key opsional ke `programs`, bukan tanda bahwa semua bantuan harus berasal dari program internal. `program_eksternal` hanya ber-FK ke `pillars` dan `users`; kolom teksnya seperti `tpb` dan `program` bukan FK ke tabel dashboard atau `programs`.

## 2. PUMK internal — mitra, kartu piutang, pelunasan, dan monitoring

```mermaid
erDiagram
    pumk_wilayah {
        bigint id PK
        varchar nama
        varchar slug UK
    }
    pumk_sektor_usaha {
        bigint id PK
        varchar nama
        varchar slug UK
    }
    pumk_mitra {
        bigint id PK
        bigint wilayah_id FK
        bigint sektor_usaha_id FK
        varchar nama_mitra
        char source_key UK
    }
    pumk_pinjaman {
        bigint id PK
        bigint mitra_id FK
        char source_key UK
        decimal pinjaman_pokok
        decimal sisa_pokok
        decimal sisa_bunga
        varchar status
    }
    pumk_saldo_awal {
        bigint id PK
        bigint pinjaman_id FK
        bigint batch_id FK
        date cutoff_date
        decimal pokok_masuk
        decimal bunga_masuk
    }
    pumk_angsuran {
        bigint id PK
        bigint pinjaman_id FK
        bigint batch_id FK
        date periode
        decimal pokok
        decimal bunga
        decimal denda
    }
    pumk_pinjaman_dokumen {
        bigint id PK
        bigint pinjaman_id FK
        varchar jenis_dokumen
        varchar file_path
    }
    pumk_loan_closures {
        bigint id PK
        bigint pinjaman_id FK
        timestamp closed_at
        timestamp reopened_at
        json settlement_snapshot
    }
    pumk_classification_history {
        bigint id PK
        bigint mitra_id FK
        bigint pinjaman_id FK
        varchar attribute
        date effective_from
    }
    pumk_import_batches {
        bigint id PK
        char file_hash UK
        date tanggal_acuan
        varchar status
    }
    pumk_import_rows {
        bigint id PK
        bigint batch_id FK
        int source_row_number
        varchar status
    }
    pumk_monitoring_reports {
        bigint id PK
        date as_of_date UK
        int revision
        tinyint needs_reconcile
    }
    pumk_monitoring_positions {
        bigint id PK
        bigint report_id FK
        bigint pinjaman_id FK
        bigint mitra_id FK
        decimal saldo_pokok
        decimal saldo_bunga
    }

    pumk_wilayah o|--o{ pumk_mitra : wilayah_id
    pumk_sektor_usaha o|--o{ pumk_mitra : sektor_usaha_id
    pumk_mitra ||--o{ pumk_pinjaman : mitra_id
    pumk_pinjaman ||--o| pumk_saldo_awal : pinjaman_id
    pumk_pinjaman ||--o{ pumk_angsuran : pinjaman_id
    pumk_pinjaman ||--o{ pumk_pinjaman_dokumen : pinjaman_id
    pumk_pinjaman ||--o{ pumk_loan_closures : pinjaman_id
    pumk_mitra o|--o{ pumk_classification_history : mitra_id
    pumk_pinjaman o|--o{ pumk_classification_history : pinjaman_id
    pumk_import_batches ||--o{ pumk_import_rows : batch_id
    pumk_import_batches o|--o{ pumk_saldo_awal : batch_id
    pumk_import_batches o|--o{ pumk_angsuran : batch_id
    pumk_monitoring_reports ||--o{ pumk_monitoring_positions : report_id
    pumk_pinjaman ||--o{ pumk_monitoring_positions : pinjaman_id
    pumk_mitra ||--o{ pumk_monitoring_positions : mitra_id
```

Kartu piutang berpusat pada `pumk_pinjaman`, dengan saldo awal, angsuran, dokumen, serta histori penutupan sebagai tabel berbeda. `pumk_saldo_awal.pinjaman_id` unik (maksimal satu saldo awal per pinjaman). `pumk_angsuran` unik pada `(pinjaman_id, periode)`; dokumen unik pada `(pinjaman_id, jenis_dokumen)`. **`pumk_loan_closures.pinjaman_id` tidak unik**, sehingga ERD tidak menyatakan satu pinjaman hanya bisa mempunyai satu catatan penutupan. Posisi monitoring unik pada `(report_id, pinjaman_id)`.

`pumk_snapshot_bulanan` tidak muncul sebagai anak `pumk_pinjaman` karena kolom bisnis tabel agregat itu adalah `(bulan, tahun, kategori, tipe, nilai)` dan **tidak mempunyai FK**. `pumk_activity_logs` juga tidak mempunyai FK langsung ke mitra/pinjaman: `entity_type` dan `entity_id` adalah rujukan generik, bukan constraint database.

## 3. PUMK BRI — sumber terpisah dari kartu piutang internal

```mermaid
erDiagram
    pumk_bri_mitra {
        bigint id PK
        varchar nama_mitra
        char source_key UK
        varchar wilayah
    }
    pumk_bri_fasilitas {
        bigint id PK
        bigint mitra_id FK
        char reference_key UK
        decimal pinjaman
        date tanggal_pencairan
    }
    pumk_bri_snapshot_bulanan {
        bigint id PK
        bigint mitra_id FK
        bigint fasilitas_id FK
        tinyint bulan
        smallint tahun
        decimal saldo_piutang
        varchar kolektibilitas_kode
    }
    pumk_bri_identity_reviews {
        bigint id PK
        bigint resolved_mitra_id FK
        bigint resolved_fasilitas_id FK
        tinyint bulan
        smallint tahun
        varchar status
    }

    pumk_bri_mitra ||--o{ pumk_bri_fasilitas : mitra_id
    pumk_bri_mitra ||--o{ pumk_bri_snapshot_bulanan : mitra_id
    pumk_bri_fasilitas o|--o{ pumk_bri_snapshot_bulanan : fasilitas_id
    pumk_bri_mitra o|--o{ pumk_bri_identity_reviews : resolved_mitra_id
    pumk_bri_fasilitas o|--o{ pumk_bri_identity_reviews : resolved_fasilitas_id
```

`pumk_bri_snapshot_bulanan.mitra_id` wajib, tetapi `fasilitas_id` boleh kosong untuk snapshot legacy. Saat terisi, kombinasi `(fasilitas_id, bulan, tahun)` unik. FK yang ada **tidak menjamin** bahwa `mitra_id` snapshot selalu sama dengan pemilik `fasilitas_id`; kesesuaian itu harus dijaga oleh logika aplikasi atau pemeriksaan data. Review identitas hanya menunjuk Mitra/Fasilitas setelah `resolved_*` diisi.

Tidak ada FK dari tabel `pumk_bri_*` ke `pumk_mitra` atau `pumk_pinjaman`. Dua dataset ini tidak boleh digabung dalam ERD seolah satu sumber pinjaman.

## 4. ERD gabungan tiga bagian

Tiga ERD di atas tetap tersedia untuk membaca detail setiap modul. Gambar berikut menyatukan tabel dan relasi utamanya dalam **satu kanvas**. `users` menghubungkan Program TJSL dengan PUMK internal melalui FK aktor yang dipilih. PUMK BRI tetap menjadi kelompok tersendiri di kanvas yang sama karena tidak ada FK dari snapshot/fasilitas BRI ke tabel PUMK internal atau Program TJSL; garis penghubung antardataset tidak dibuat-buat.

![ERD gabungan Program TJSL, PUMK internal, dan PUMK BRI](output/erd/ERD-TJSLINKA-gabungan.svg)

[Buka gambar SVG ukuran penuh](output/erd/ERD-TJSLINKA-gabungan.svg) · [Sumber diagram Mermaid](output/erd/ERD-TJSLINKA-gabungan.mmd)

## 5. Tabel agregat, referensi, dan infrastruktur tanpa FK antartabel bisnis

| Kelompok | Tabel yang benar-benar ada | Kunci atau fungsi yang terlihat dari skema |
|---|---|---|
| Agregat PUMK BRI | `pumk_bri_ringkasan`, `pumk_bri_rka_tahunan`, `pumk_bri_penyaluran_bulanan`, `pumk_bri_saldo_bulanan`, `pumk_bri_sektor`, `pumk_bri_kualitas` | Unik menurut `tahun`, `(tahun, bulan)`, kategori-periode, atau nama/kategori masing-masing; **tidak saling ber-FK** dan tidak ber-FK ke snapshot. |
| Agregat PUMK internal | `pumk_snapshot_bulanan` | Unik pada `(bulan, tahun, kategori, tipe)`; tidak ber-FK ke pinjaman. |
| Dashboard/referensi TJSL | `bidang_prioritas`, `tpb_dashboard`, `wilayah_operasional` | Berdiri sendiri; `nama_bidang` dan `nomor_tpb` unik. |
| Infrastruktur Laravel | `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions` | Ada dalam database, tetapi tidak memiliki constraint FK ke tabel bisnis. `sessions.user_id` dan `password_reset_tokens.email` bukan FK fisik. |

Periode/tahun yang sama di dua tabel agregat bukan otomatis relasi entitas. Diagram tidak menggambar garis untuk kemiripan nama kolom atau hasil join di kode jika constraint FK tidak ada.

## 6. Relasi pengguna dan audit yang tidak ditarik di diagram utama

Semua kolom di bawah ini **FK fisik ke `users.id`**. Kolom `created_by` pada `programs`, `bantuan_csr`, `program_eksternal`, `teras_produk`, `teras_paket` dan `changed_by` pada `status_logs` wajib terisi; FK pengguna lainnya pada daftar ini boleh `NULL`, kecuali `notifications.user_id` yang juga wajib.

| Tabel | Kolom FK ke `users.id` |
|---|---|
| `programs` | `created_by`, `reviewed_by`, `fase1_reviewed_by`, `fase2_reviewed_by` |
| `bantuan_csr` | `created_by`, `reviewed_by`, `fase1_reviewed_by`, `fase2_reviewed_by` |
| `program_eksternal` | `created_by`, `tahap2_reviewed_by`, `tahap3_reviewed_by` |
| `notifications` | `user_id` |
| `status_logs` | `changed_by` |
| `teras_produk`, `teras_paket` | `created_by` |
| `pumk_mitra` | `created_by` |
| `pumk_pinjaman` | `created_by`, `lunas_by` |
| `pumk_pinjaman_dokumen` | `uploaded_by` |
| `pumk_angsuran` | `created_by` |
| `pumk_import_batches` | `imported_by` |
| `pumk_classification_history` | `recorded_by` |
| `pumk_loan_closures` | `closed_by`, `reopened_by` |
| `pumk_activity_logs` | `actor_user_id` |
| `pumk_bri_rka_tahunan`, `pumk_bri_penyaluran_bulanan` | `created_by`, `updated_by` |

`status_logs.related_type` + `related_id`, `notifications.related_type` + `related_id`, serta `pumk_activity_logs.entity_type` + `entity_id` adalah rujukan bertipe generik. Ketiganya **bukan FK fisik** ke tabel program, bantuan, atau PUMK. Tabel `users` menyimpan role akun dalam satu tabel; tidak ada tabel role terpisah pada skema ini.

## 7. Cara membaca dan memverifikasi ulang

- `PK` = primary key, `FK` = foreign key, `UK` = unique key/indeks unik. Untuk kunci gabungan, rinciannya disebut dalam catatan, bukan ditandai `UK` pada tiap kolom secara terpisah.
- Diagram ini menggambarkan **skema saat diperiksa**, bukan klaim tentang seluruh aturan bisnis di kode atau isi tiap record. Ada tabel tanpa FK yang tetap dipakai aplikasi.
- Sumber pembanding dalam repo: `database/migrations/` dan model di `app/Models/`. Untuk pemeriksaan ulang terhadap database aktif, gunakan metadata `information_schema.COLUMNS`, `KEY_COLUMN_USAGE`, dan `STATISTICS` pada koneksi Laravel lokal; jangan menyimpulkan relasi hanya dari nama kolom.
