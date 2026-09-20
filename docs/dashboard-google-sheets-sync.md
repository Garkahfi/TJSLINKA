# Sinkronisasi Google Sheets - Dashboard Program TJSL

Dashboard Program TJSL tetap membaca cache dari database. Google Sheets hanya menjadi sumber pembaruan untuk data Pilar, Wilayah, Bidang Prioritas, dan TPB. Dashboard Program PUMK tidak termasuk integrasi ini.

## 1. Siapkan Google Sheets

Buat satu spreadsheet dengan empat tab berikut. Nama header harus sama persis dan berada di baris pertama.

### Tab `Pilar`

```csv
nama_pilar,rencana_anggaran,realisasi_anggaran
```

Nilai `nama_pilar` harus cocok dengan pilar yang sudah ada, misalnya `Sosial`, `Ekonomi`, `Lingkungan`, atau `Hukum & Tata Kelola`.

### Tab `Wilayah`

```csv
nama_wilayah,realisasi_anggaran
```

Nama wilayah harus cocok dengan data `wilayah_operasional`. Sinkronisasi hanya memperbarui realisasi anggaran; latitude dan longitude tidak pernah ditimpa dari Google Sheets.

### Tab `BidangPrioritas`

```csv
nama_bidang,rencana_anggaran,realisasi_anggaran,penyerapan_persen
```

Jika `penyerapan_persen` dikosongkan sementara rencana dan realisasi tersedia, sistem menghitung persentasenya otomatis.

### Tab `TPB`

```csv
nomor_tpb,nama_tpb,rencana_anggaran,realisasi_anggaran
```

`nomor_tpb` boleh ditulis `1` atau `TPB 1`; sistem menyimpannya dengan nomor yang konsisten.

Untuk setiap tab:

1. Pilih **File -> Share -> Publish to web**.
2. Pilih tab yang sesuai, lalu pilih format **Comma-separated values (.csv)**.
3. Pastikan hasil publikasi dapat dibuka tanpa login/OAuth.
4. Salin URL CSV masing-masing tab.

## 2. Isi konfigurasi `.env`

```dotenv
GOOGLE_SHEET_URL_PILAR=https://docs.google.com/...&output=csv
GOOGLE_SHEET_URL_WILAYAH=https://docs.google.com/...&output=csv
GOOGLE_SHEET_URL_BIDANG_PRIORITAS=https://docs.google.com/...&output=csv
GOOGLE_SHEET_URL_TPB=https://docs.google.com/...&output=csv
```

Setelah mengubah `.env` di server yang menggunakan config cache, jalankan:

```bash
php artisan config:clear
php artisan config:cache
```

## 3. Uji manual di lokal

```bash
php artisan migrate
php artisan dashboard:sync-sheets
```

Periksa tabel `pillars`, `wilayah_operasional`, `bidang_prioritas`, dan `tpb_dashboard`, lalu buka halaman Home publik.

`php artisan serve` tidak menjalankan scheduler. Untuk menjalankan scheduler terus-menerus saat development, buka terminal terpisah:

```bash
php artisan schedule:work
```

## 4. Aktifkan scheduler di production

Laravel sudah menjadwalkan command setiap 15 menit. Server tetap harus memanggil scheduler Laravel setiap menit:

```cron
* * * * * cd /path-ke-project && php artisan schedule:run >> /dev/null 2>&1
```

Tambahkan baris tersebut melalui `crontab -e` dan sesuaikan `/path-ke-project` dengan lokasi aplikasi di server. Untuk server Windows, buat Windows Task Scheduler yang menjalankan `php artisan schedule:run` setiap satu menit dari direktori proyek.

## Perilaku saat terjadi gangguan

- Setiap tab diproses secara independen.
- URL kosong, respons HTTP gagal, header salah, atau data angka tidak valid membuat tab itu dilewati.
- Kegagalan satu tab tidak menghentikan sinkronisasi tiga tab lainnya.
- Data cache lama tidak dihapus atau dikosongkan ketika fetch gagal.
- Detail error dicatat di `storage/logs/laravel.log`.
- Baris Pilar atau Wilayah dengan nama yang belum ada di database dilewati agar metadata pilar dan koordinat wilayah tidak dibuat secara sembarang.
