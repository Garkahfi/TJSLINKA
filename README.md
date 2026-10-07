# TJSLINKA — Lensa TJSL INKA

Aplikasi Laravel untuk Program/Bantuan TJSL serta PUMK internal (kartu piutang) dan monitoring PUMK BRI. Data PUMK internal dan PUMK BRI adalah dua sumber yang berbeda; jangan menggabungkannya hanya karena keduanya muncul pada dashboard.

## Serah-terima untuk pengembang berikutnya

Repositori ini berisi kode, migrasi, pengujian, dan dokumentasi yang dilacak Git. **Database aktif, berkas unggahan, dan rahasia aplikasi tidak ada di GitHub.** Mintalah paket data serta akses yang disetujui dari tim IT; jangan memasukkan dump SQL, dokumen mitra, atau `.env` ke commit.

Kebutuhan pengembangan: PHP 8.3+, Composer, Node.js/npm, dan MySQL untuk data aplikasi. Mulai dari branch `main`:

```powershell
git clone https://github.com/Garkahfi/TJSLINKA.git
cd TJSLINKA
composer install
npm ci
npm run build
php scripts/init-env.php
```

Skrip terakhir hanya membuat `.env` lokal minimal bila belum ada. Minta tim IT mengisi koneksi database dan memberikan `APP_KEY` yang sesuai melalui kanal rahasia. Untuk **salinan database lama yang sudah berisi data terenkripsi**, jangan menjalankan `php artisan key:generate` atau `composer run setup`: skrip setup juga menjalankan migrasi. Pulihkan dump ke database terpisah sesuai arahan IT, lalu tempatkan berkas unggahan pada `storage/app/private` dan `storage/app/public` dengan struktur aslinya. Jangan menjalankan `migrate:fresh` atau `db:wipe` pada salinan data tersebut.

Setelah konfigurasi dan data tersedia:

```powershell
php artisan config:clear
php artisan storage:link
php artisan serve
```

Pengujian otomatis bawaan menggunakan SQLite in-memory sebagaimana diatur dalam `phpunit.xml`; hasilnya **bukan** pengganti UAT aplikasi dan verifikasi data MySQL yang dipulihkan:

```powershell
php artisan test
```

## Peta dokumentasi

- [ERD TJSLINKA](ERD-TJSLINKA.md) — tiga modul dan satu diagram gabungan; hanya relasi FK yang benar-benar ada yang digambar.
- [Panduan UAT](docs/uat-tjslinka.md) — skenario pengujian per peran dan modul.
- [Pemetaan dan rekonsiliasi piutang PUMK](docs/pumk-piutang-reconciliation.md).
- [Audit performa](docs/reports/audit-performa-tjslinka.md).
- [Catatan pelunasan dan arsip PUMK](docs/pumk-settlement-archive-fix.md).

Sebelum mengubah logika saldo atau kolektibilitas, baca dokumen rekonsiliasi dan jalankan tes terkait. Jangan memakai data pribadi nyata untuk pengujian otomatis atau mengunggahnya ke issue/PR.
