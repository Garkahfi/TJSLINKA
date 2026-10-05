# Audit performa TJSLINKA — 5 Oktober 2026

## Lingkup dan keselamatan

Baseline adalah kode lokal pada branch `codex/pumk-formula-refactor-20261004`, HEAD `6d493b6096970523bce4550d28a8e852f52a4b7c`, **termasuk refaktor lokal yang belum di-commit**. Perubahan lain di worktree, termasuk video, tidak disentuh. Tidak ada import, migrasi, seeding, pembayaran, atau perubahan data pada database aplikasi. Tidak ada push/deploy.

Lingkungan: PHP CLI 8.3.30, Laravel 13.20.0, Windows, CLI tanpa ekstensi OPcache, `APP_DEBUG=true` lokal. Konfigurasi efektif aplikasi: MySQL di `127.0.0.1:3306`. Query baca-saja pada MySQL 8.4.3 mencatat jumlah baris saat audit: 395 mitra PUMK, 396 pinjaman PUMK, 95 angsuran, 925 snapshot BRI, 27 program TJSL, dan 9 bantuan CSR. Tidak ada isi identitas yang dicetak. MySQL sempat tidak merespons pada awal audit, kemudian tersedia; **semua angka benchmark di bawah tetap berasal dari SQLite `:memory:` terisolasi, bukan MySQL**.

## Metode

Harness eksplisit: `php vendor/bin/phpunit tests/Benchmarks/TjslinkaPerformanceBenchmark.php`. `tests/TestCase.php` membatalkan test jika lingkungan bukan `testing` + SQLite `:memory:`. Fixture sintetis dengan waktu tetap `2026-10-05 12:00 Asia/Jakarta`: 200 mitra/400 pinjaman internal (dua pinjaman per mitra), 1.600 angsuran, 100 mitra/fasilitas BRI dengan 800 snapshot sepanjang delapan bulan, 100 program, dan 100 bantuan. Ini **bukan ukuran data kantor**. Fixture dibuat di luar jendela ukur. Setiap skenario HTTP memakai 2 warm-up dan 7 request terukur, berurutan, pada worker PHPUnit yang sama. Collector `DB::listen` tunggal diaktifkan hanya sepanjang request; query auth dan view ikut dihitung. Waktu memakai `hrtime`, waktu SQL adalah jumlah `QueryExecuted::time`, ukuran respons adalah byte HTML. Tidak ada binding atau isi pribadi dalam output benchmark; SQL fingerprint pada harness terbaru berupa hash. Waktu ini adalah request HTTP Laravel test client, **bukan waktu browser/jaringan**.

### HTTP sebelum → sesudah

| Skenario fixture | Query | SQL p50 (ms) | HTTP p50 (ms) | Rentang HTTP sebelum → sesudah (ms) | Kalkulator | Respons byte sebelum → sesudah |
|---|---:|---:|---:|---:|---:|---:|
| Kartu aktif, 400 pinjaman tersedia | 7 → 7 | 6,51 → 1,16 | 333,85 → 27,80 | 280,60–471,68 → 27,04–30,81 | 2 → 1 | 58.023 → 58.019 |
| Daftar mitra aktif, halaman 15 mitra | 10 → 10 | 63,56 → 10,96 | 1.106,92 → 105,51 | 716,44–1.819,86 → 90,67–109,47 | 30 → 30 | 49.429 → 49.425 |
| Filter kategori “Lancar” (hasil kosong), scope 400 pinjaman | 18 → 11 | 271,20 → 21,79 | 24.053,58 → 885,84 | 22.074,15–26.265,06 → 838,77–960,08 | 800 → 400 | 21.439 → 21.435 |
| Monitoring INKA 2026, 400 pinjaman | 9 → 9 | 20,56 → 17,44 | 6.185,16 → 3.827,76 | 4.440,69–7.148,64 → 3.688,33–4.560,67 | 0 → 0 | 87.881 → 87.881 |
| Monitoring BRI 2026, 100 fasilitas/800 snapshot | 19 → 19 | 3,96 → 4,21 | 27,29 → 29,04 | 25,22–28,41 → 25,83–31,79 | 0 → 0 | 100.458 → 100.458 |
| Daftar 100 program TJSL | 3 → 3 | 1,08 → 1,20 | 62,11 → 62,45 | 57,97–70,32 → 57,52–73,22 | 0 → 0 | 121.890 → 121.890 |
| Daftar 100 bantuan CSR | 4 → 4 | 1,08 → 1,17 | 88,06 → 101,09 | 79,33–93,56 → 88,14–117,68 | 0 → 0 | 103.189 → 103.189 |

**Batas interpretasi:** Putaran baseline pertama juga jauh lebih lambat pada kartu dan daftar *tanpa perubahan jalur query*, jadi angka HTTP antarputaran dipengaruhi kondisi runtime/worker yang tidak berhasil diisolasi sepenuhnya. Jangan mengklaim percepatan 10–30× pada aplikasi nyata dari tabel ini. Penurunan query dan jumlah kalkulasi adalah bukti deterministik; perbandingan berpasangan berikut lebih dapat dipercaya untuk biaya loop kategori. Perubahan 4 byte pada beberapa respons bukan perubahan data bisnis: signature data terstruktur tetap identik, tetapi HTML mentah tidak dibandingkan byte demi byte. Tidak ada p95 dari tujuh sampel.

### Perbandingan berpasangan pada proses yang sama

Harness juga menjalankan lintasan lama `matchingLoanIds()` + `summarize()` dan lintasan baru `matchingLoanIdsWithSummary()` secara berselang-seling terhadap **400 pinjaman yang semuanya cocok kategori Macet**, masing-masing 2 warm-up + 7 run. Kedua hasil ID dan rekap di-assert identik pada setiap pasangan.

| Lintasan | Query | SQL p50 | Waktu service p50 | Kalkulator | Peak memory terukur |
|---|---:|---:|---:|---:|---:|
| Dua lintasan lama | 14 | 31,63 ms | 2.042,35 ms | 800 | 65.011.712 byte |
| Satu lintasan baru | 7 | 15,43 ms | 1.109,50 ms | 400 | 65.011.712 byte |

Peak direset dengan `memory_reset_peak_usage()` sebelum setiap lintasan, tetapi angka granularitas allocator mencakup worker PHPUnit dan fixture yang sudah berada di memori. Hasilnya hanya menunjukkan **tidak ada penurunan peak terukur** pada proses ini, bukan klaim penggunaan memori produksi.

## Temuan dan perubahan terarah

1. `PumkMitraPages::index` dahulu memanggil `matchingLoanIds()` lalu `summarize()` untuk filter kategori. Keduanya memindai scope yang sama per chunk dan menghitung posisi tiap pinjaman lagi. Ini kalkulasi berulang, **bukan N+1**. Sekarang `PumkCollectibilitySummaryService::matchingLoanIdsWithSummary()` menghasilkan ID dan rekap dalam satu lintasan. Scope pemilik, pengecualian source key, status aktif/lunas, BCMath 20 digit, rounding di akhir, filter sebelum pagination, dan subtotal seluruh scope tetap dipertahankan. Daftar tanpa filter kategori tidak berubah.
2. Pada halaman kartu aktif, `KartuPiutangService::buat()` dan pratinjau pelunasan menghitung posisi kantor untuk loan/tanggal yang sama. `PumkMitraPages::show` sekarang menyerahkan hasil kalkulator kartu saat ini kepada `PumkLoanSettlementService::preview`. POST pelunasan masih menghitung ulang di transaksi; tidak memakai cache hasil render. Hasil baca berikut setelah pembayaran juga dihitung ulang.
3. `PumkInternalMonitoringService::report` menghitung posisi `asOf`, kemudian tren bulanan menghitung ulang tanggal yang sama bila tanggal itu merupakan titik tren. `PumkInternalTrendBuilder` sekarang menggunakan posisi yang sudah dihitung **hanya jika tanggal sama**; bulan lain tetap dihitung sendiri. Test counter membuktikan satu panggilan posisi lebih sedikit dengan hasil tren identik.
4. Monitoring BRI, daftar program TJSL, dan daftar bantuan CSR menunjukkan jumlah query tetap pada fixture ini (19/3/4). Query BRI adalah agregasi dengan makna berbeda; tidak ada bukti N+1 atau keharusan menggabungkannya. Eager loading dan pagination daftar mitra aktif sudah ada. Tidak ada indeks atau perubahan SQL agregasi yang ditambahkan. Selisih waktu kecil pada jalur tak diubah adalah variabilitas pengukuran.

## Kesetaraan dan pemeriksaan yang dijalankan

- Signature data terstruktur fixture 400 pinjaman **sama sebelum dan sesudah**: `9f40ac6acb0d9cd4ff0336f700873b34244bd9a6f22d59a4c4f63dddf74ce8fe`. Signature mencakup hasil kalkulator kartu dan tahun/baris, ID mitra pada filter dan footer nominal, laporan INKA/BRI (kecuali `updated_at`), serta ID daftar TJSL/CSR. Pada fixture ini filter Lancar kebetulan kosong; pengujian berpasangan Macet mencakup 400 hasil nonkosong.
- Baseline test terpilih sebelum edit: **48 tes, 419 assertion, lulus**. Sesudah perubahan: **151 tes relevan, 1.648 assertion, lulus**; seluruh suite aplikasi pada putaran final **320 tes, 3.605 assertion, lulus**. Test tren tanggal-identik juga diuji sendiri bersama file monitoring: **22 tes, 147 assertion, lulus**. Test kategori mencakup multi-pinjaman, arsip, nominal negatif, filter dan subtotal; test preview mencakup kesetaraan serta pembacaan baru setelah pembayaran. Existing tests mencakup histori/closing, carryover, BRI beberapa tahun, filter status dan akses admin.
- Syntax check PHP pada semua file produksi yang diubah: lulus. Pint `--test` pada file terkait: lulus setelah formatting. `git diff --check`: lulus. Tidak ada Blade atau aset yang diubah oleh pekerjaan audit ini, sehingga build aset tidak dijalankan.

## Batasan dan tindak lanjut

- Fixture HTTP utama belum menyertakan closure/reopen, classification history, dan dokumen pada skala 400; jalur itu ditangani test fungsional, bukan klaim performa skala penuh. Dataset 4.000 tidak dijalankan karena run baseline 400 pertama mencapai sekitar 349 detik dan stabilitas waktu antarputaran belum cukup baik. Tidak ada klaim kapasitas 4.000.
- Tidak ada benchmark performa MySQL maupun EXPLAIN pada salinan MySQL testing yang setara. Database MySQL lokal hanya dibaca untuk versi/jumlah baris; jangan menganggap hasil SQLite sebagai estimasi waktu produksi atau justifikasi indeks MySQL. Tidak ada migration indeks yang diajukan/diterapkan.
- UAT browser nyata masih diperlukan untuk latensi halaman kartu dan filter kategori pada komputer/jaringan target serta pemeriksaan visual yang tidak terwakili PHPUnit. Bila ingin menilai indeks/kapasitas, siapkan salinan MySQL testing tersanitasi dengan ukuran dan indeks setara, lalu ulangi benchmark satu worker tanpa beban lain.
- Perubahan audit ini masih lokal dan belum di-commit. File/refaktor lain yang sudah ada sebelum audit tetap dipertahankan; tidak ada push atau deploy.
