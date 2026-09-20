# Perbaikan: Halaman Kartu Piutang HARUS Sesuai Layout Kartu Fisik Asli

Halaman Kartu Piutang yang sudah dibuat Codex sebelumnya BELUM sesuai — itu cuma nampilin daftar pembayaran yang sudah terjadi (sparse list). Yang benar: **tampilkan jadwal angsuran PENUH dari awal sampai akhir tenor**, persis seperti kartu piutang fisik program Kemitraan dan Bina Lingkungan (PKBL) yang sudah ada. Analisis di bawah ini berdasarkan contoh kartu asli.

## 1. Header — 2 Kolom Info

**Kolom kiri** (info dasar pinjaman):
```
Nama Perusahaan  : {pumk_mitra.nama_mitra}
Pemilik          : {pumk_mitra.nama_pemilik}
Angsuran Pertama : {pumk_pinjaman.mulai_angsuran, format "Bulan Tahun"}
Jatuh Tempo      : {pumk_pinjaman.selesai_angsuran, format "Bulan Tahun"} ({tenor}x)
Jumlah Pinjaman  : Rp {pumk_pinjaman.pinjaman_pokok}
```

**Kolom kanan** (kotak "KARTU PIUTANG"):
```
[Judul: KARTU PIUTANG]
{pumk_mitra.wilayah}
{"Rescheduling" — tampilkan HANYA kalau reschedule_ke1/2/3 salah satu terisi}
Bunga   : Rp {pumk_pinjaman.pinjaman_bunga}
Angs/bln: Rp {pumk_pinjaman.nilai_angsuran_bulanan}
```

⚠️ **Catatan soal "tahun" di kotak kanan** (contoh kartu fisik nunjukin "KOTA MADIUN **2024** Rescheduling"): TIDAK ADA kolom database yang jelas jadi sumber angka tahun ini — nggak pasti itu `tahun_pencairan`, tahun kontrak reschedule terakhir, atau field lain yang belum ada. **JANGAN ditebak/dipaksa isi dari field manapun** — untuk sekarang, tampilkan cuma Wilayah + label "Rescheduling" (kalau ada), TANPA angka tahun. Kalau nanti user kasih kepastian field mana yang harusnya diisi ke situ, baru ditambahkan.

## 1b. Relasi Mitra ↔ Pinjaman ↔ Kartu Piutang

Untuk data yang ada SEKARANG, asumsikan **1 mitra = 1 pinjaman = 1 Kartu Piutang** (kasus umum/mayoritas). TAPI skema `pumk_pinjaman` sudah dirancang `mitra_id` sebagai foreign key biasa (bukan `unique`), jadi SECARA STRUKTUR sudah mendukung kalau suatu saat ada mitra dengan lebih dari 1 pinjaman (dapat pinjaman baru terpisah, bukan reschedule dari yang lama) — itu nanti otomatis berarti mitra itu punya lebih dari 1 Kartu Piutang. TIDAK PERLU ubah skema untuk ini SEKARANG, cukup pastikan halaman "Daftar Mitra Binaan" → tombol "Lihat Kartu" itu mengarah ke pinjaman yang benar (kalau baru ada 1 pinjaman per mitra di data sekarang, ini nggak akan jadi masalah praktis).

## 2. Field Baru yang Dibutuhkan

### Tambah kolom `nomor_bukti` di `pumk_angsuran` (BELUM ADA sebelumnya)

```php
Schema::table('pumk_angsuran', function (Blueprint $table) {
    $table->string('nomor_bukti')->nullable()->after('periode');
});
```

### Hitung `tenor` (jumlah bulan cicilan)

Tidak perlu kolom baru — hitung dari selisih bulan antara `mulai_angsuran` dan `selesai_angsuran` (contoh: Januari 2026 ke Desember 2029 = 48 bulan). Kalau mau lebih aman/eksplisit, boleh tambah kolom `tenor_bulan` (integer, nullable) supaya tidak recalculate terus-terusan — pilih salah satu, yang penting konsisten.

## 3. Tabel Jadwal Angsuran — INI YANG PALING PENTING

**Generate SEMUA baris dari 1 sampai tenor** (misal 48 baris untuk tenor 48 bulan), BUKAN cuma baris yang sudah ada pembayarannya:

```php
public function jadwalAngsuran(PumkPinjaman $pinjaman): array
{
    $jadwal = [];
    $saldoPokok = $pinjaman->pinjaman_pokok;
    $saldoBunga = $pinjaman->pinjaman_bunga;
    $tanggal = \Carbon\Carbon::parse($pinjaman->mulai_angsuran);

    // ambil semua pembayaran aktual, index berdasarkan periode (Y-m) biar gampang dicocokkan
    $pembayaran = $pinjaman->angsuran()->get()->keyBy(fn($a) => \Carbon\Carbon::parse($a->periode)->format('Y-m'));

    $tenor = $tanggal->diffInMonths(\Carbon\Carbon::parse($pinjaman->selesai_angsuran)) + 1;

    for ($i = 1; $i <= $tenor; $i++) {
        $periodeKey = $tanggal->format('Y-m');
        $bayar = $pembayaran->get($periodeKey);

        if ($bayar) {
            $saldoPokok -= $bayar->pokok;
            $saldoBunga -= $bayar->bunga;
        }

        $jadwal[] = [
            'no' => $i,
            'tanggal' => $tanggal->copy(),
            'nomor_bukti' => $bayar->nomor_bukti ?? null,
            'pokok_dibayar' => $bayar->pokok ?? null,   // null → tampil "-"
            'bunga_dibayar' => $bayar->bunga ?? null,
            'total_dibayar' => $bayar->total ?? null,
            'saldo_pokok' => $saldoPokok,   // TETAP sama kalau bulan ini belum bayar
            'saldo_bunga' => $saldoBunga,
            'is_bulan_berjalan' => $tanggal->isSameMonth(now()) && $tanggal->isSameYear(now()),
        ];

        $tanggal->addMonth();
    }

    return $jadwal;
}
```

Blade view: render tabel dengan kolom **No | Tanggal | Nomor Bukti Pembayaran | Pokok | Bunga | Total | Saldo Pokok | Saldo Bunga** — kolom Pokok/Bunga/Total tampilkan `"-"` kalau null (belum bayar bulan itu), Saldo Pokok/Bunga SELALU tampil angka di setiap baris (bukan cuma baris yang ada pembayaran).

**Baris "bulan berjalan"** (`is_bulan_berjalan = true`) dikasih highlight warna beda (kayak baris kuning/oranye muda di kartu fisik) — biar Admin PUMK langsung lihat "sekarang lagi di bulan ke berapa".

## 4. Baris "Kekurangan" di Bagian Bawah Tabel

```blade
<div class="text-right font-bold">
    Kekurangan: Rp {{ number_format($jadwal[count($jadwal)-1]['saldo_pokok'] + $jadwal[count($jadwal)-1]['saldo_bunga'], 0, ',', '.') }}
</div>
```

Ini sama dengan sisa total pinjaman (pokok + bunga) berdasarkan saldo baris TERAKHIR yang sudah dihitung — kalau belum ada pembayaran sama sekali, nilainya = Jumlah Pinjaman + Bunga (persis kayak contoh kartu: 26.658.550 + 2.400.000 = 29.058.550).

## 5. Form "Tambah Angsuran" — Tambah Field Nomor Bukti

Form yang sudah ada sebelumnya (Periode, Pokok, Bunga, Denda) perlu tambah 1 field: **Nomor Bukti Pembayaran** (teks bebas).

## 6. Query DB — Supaya Sinkron & Tidak Berat Meski Datanya Banyak (394+ Mitra)

Prinsip pembagian tugas query:
- **Halaman Detail (Kartu Piutang satu mitra)** → boleh generate jadwal 48 bulan LIVE (Bagian 3), karena cuma 1 record, ringan.
- **Halaman List (Daftar Mitra Binaan, 394 baris) & Dashboard Home** → JANGAN panggil `jadwalAngsuran()` 394 kali. Pakai kolom yang SUDAH DIHITUNG & DISIMPAN (`sisa_pokok`, `sisa_bunga`, `total_sisa`, `kolektibilitas` di tabel `pumk_pinjaman`), bukan hitung ulang tiap kali halaman dibuka.

### 6.1 Observer — Jaga Kolom Cache Tetap Sinkron Otomatis

Supaya kolom `sisa_pokok`/`kolektibilitas`/dst di `pumk_pinjaman` SELALU akurat tanpa harus dihitung ulang tiap load halaman, buat Observer yang jalan otomatis tiap ada perubahan data pembayaran:

```php
php artisan make:observer PumkAngsuranObserver --model=PumkAngsuran
```

```php
class PumkAngsuranObserver
{
    public function saved(PumkAngsuran $angsuran): void
    {
        $this->syncPinjaman($angsuran->pinjaman);
    }

    public function deleted(PumkAngsuran $angsuran): void
    {
        $this->syncPinjaman($angsuran->pinjaman);
    }

    private function syncPinjaman(PumkPinjaman $pinjaman): void
    {
        $hasil = app(\App\Services\Pumk\PiutangCalculator::class)->hitungUntukPinjaman($pinjaman);
        $pinjaman->update($hasil); // update sisa_pokok, sisa_bunga, kolektibilitas, dst SEKALI di sini
    }
}
```

Daftarkan di `AppServiceProvider::boot()`:
```php
PumkAngsuran::observe(PumkAngsuranObserver::class);
```

Efeknya: kolom cache di `pumk_pinjaman` ke-update OTOMATIS pas Admin PUMK tambah/edit/hapus angsuran (satu kali hitung, langsung simpan) — bukan dihitung ulang tiap kali ada yang buka halaman list/dashboard. Ini yang bikin "sinkron tanpa berat".

### 6.2 Halaman List — Query Ringan, Bukan Loop 394x

```php
// SALAH — jangan panggil jadwalAngsuran() di dalam loop 394 mitra
// foreach ($mitraList as $mitra) { $this->jadwalAngsuran($mitra->pinjaman); }

// BENAR — cukup baca kolom yang sudah di-cache Observer
$mitraList = PumkMitra::with(['wilayah', 'sektorUsaha', 'pinjaman'])  // eager load, cegah N+1 query
    ->when($request->wilayah, fn($q) => $q->where('wilayah_id', $request->wilayah))
    ->when($request->sektor, fn($q) => $q->where('sektor_usaha_id', $request->sektor))
    ->when($request->kolektibilitas, fn($q) => $q->whereHas('pinjaman', fn($q2) =>
        $q2->where('kolektibilitas', $request->kolektibilitas)))
    ->paginate(15);
```

`with([...])` WAJIB dipakai — tanpa itu, tiap baris di tabel bakal trigger query terpisah buat ambil data wilayah/sektor/pinjaman (394 baris × 3 relasi = ribuan query kecil, ini yang paling sering bikin halaman list jadi lambat kalau kelewatan).

### 6.3 Dashboard Home — Agregat Langsung dari Kolom Cache

```php
$totalMitra = PumkMitra::where('is_active', true)->count();
$totalPinjaman = PumkPinjaman::count();
$kolektibilitas = PumkPinjaman::selectRaw('kolektibilitas, COUNT(*) as jumlah')
    ->groupBy('kolektibilitas')
    ->pluck('jumlah', 'kolektibilitas');
```

Ini query `COUNT`/`GROUP BY` biasa — ringan bahkan untuk ribuan baris sekalipun, TIDAK butuh caching tambahan di skala data sekarang (394 mitra). Jangan over-engineer dengan Redis/cache layer di tahap ini — itu baru relevan kalau datanya sudah puluhan ribu baris.

### 6.4 Index yang Perlu Dipastikan Ada

```php
// kalau belum ada dari migration sebelumnya, tambahkan:
Schema::table('pumk_mitra', function (Blueprint $table) {
    $table->index('wilayah_id');
    $table->index('sektor_usaha_id');
});
```
(`pumk_pinjaman.kolektibilitas` sudah ada index-nya dari migration sebelumnya — pastikan belum ke-drop.)

## Test Manual
1. Buka Kartu Piutang mitra yang BELUM PERNAH bayar sama sekali — pastikan tabel tetap nampilin SEMUA baris sesuai tenor (misal 48 baris), semua kolom pembayaran "-", saldo pokok/bunga SAMA di semua baris (belum berkurang), dan baris bulan berjalan ke-highlight.
2. Tambah 1 angsuran untuk bulan tertentu (isi Nomor Bukti, Pokok, Bunga) — cek baris bulan itu di Kartu Piutang sekarang nampilin angka pembayaran + Nomor Bukti, DAN saldo pokok/bunga di baris itu DAN SEMUA BARIS SETELAHNYA berkurang sesuai.
3. Cek angka "Kekurangan" di bawah tabel — harus sama dengan saldo pokok+bunga di baris paling akhir/terbaru.
4. Bandingkan tampilan keseluruhan (header 2 kolom, kotak "KARTU PIUTANG", format tabel) dengan contoh kartu fisik — harus mirip secara struktur, bukan cuma datanya benar tapi layoutnya beda.
