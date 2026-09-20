# Design Tokens — LENSA TJSL INKA
## Warna Terverifikasi (diekstrak langsung dari file desain, BUKAN estimasi visual)

Dokumen ini menggantikan bagian "Design Tokens (estimasi visual)" di brief Fase 1 & Fase 2. Semua kode hex di bawah diambil langsung dengan sampling pixel dari file PNG asli Figma, jadi bisa dipakai persis apa adanya di Tailwind config — bukan tebakan lagi. Ini kemungkinan besar akar masalah kenapa hasil Codex sebelumnya nggak match: tanpa nilai pasti, Codex/AI manapun akan menebak-nebak warna sendiri.

---

## 1. Warna Pilar — Versi "Cerah" (badge, kartu, bar status, kategori publik)

Dipakai di: kartu statistik Home Admin, bar warna di list Status/Arsip Program, badge pilar di halaman publik.

| Pilar | Hex | Tailwind terdekat |
|---|---|---|
| Sosial | `#2563eb` | `blue-600` |
| Ekonomi | `#f59e0b` | `amber-500` |
| Lingkungan | `#16a34a` | `green-600` |
| Hukum & Tata Kelola | `#dc2626` | `red-600` |

## 2. Warna Pilar — Versi "Gelap/Muted" (tombol Kategori Program di form Admin)

⚠️ **Ini BUKAN warna yang sama dengan tabel di atas** — form "Buat Program Baru" / "Buat Bantuan CSR" pakai versi lebih gelap khusus untuk tombol pemilihan kategori. Jangan disamakan.

| Pilar | Hex |
|---|---|
| Sosial | `#0a4e6f` |
| Ekonomi | `#8f8321` |
| Lingkungan | `#2c691c` |
| Hukum & Tata Kelola | `#850e0d` |

## 3. Tombol Aksi — Dashboard Admin

| Fungsi Tombol | Hex | Contoh Pemakaian |
|---|---|---|
| Primary Blue | `#2653ff` | "Simpan dan Ajukan Kepada Super Admin", "Tambah Dokumen", "Tambah Foto Dokumentasi", "Lihat" |
| Magenta/Pink | `#fd6eff` | "Simpan Draft", "Ganti" |
| Merah Upload | `#ff0009` | "Unggah" |
| Biru Muda | `#7fa4e4` | "Batal", "Hapus", "Batal Ajukan Kepada Super Admin" |

⚠️ **Catatan penting:** Primary Blue tombol (`#2653ff`) sedikit berbeda dari Biru Pilar Sosial (`#2563eb`) di Bagian 1 — mirip tapi bukan warna yang sama persis. Pastikan Codex tidak menyamakan/membulatkan keduanya jadi satu variabel warna.

## 4. Netral & Layout

| Elemen | Hex |
|---|---|
| Background halaman (page bg) | `#f5f5f5` |
| Input/field disabled atau readonly | `#eeeeee` |
| Putih (card, navbar bg) | `#ffffff` |
| Footer / section gelap | `#202329` |
| Teks sekunder/abu-abu | `#60636b` |

## 5. Cara Pakai di Tailwind Config

```js
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        pillar: {
          sosial: '#2563eb',
          ekonomi: '#f59e0b',
          lingkungan: '#16a34a',
          hukum: '#dc2626',
        },
        'pillar-dark': {
          sosial: '#0a4e6f',
          ekonomi: '#8f8321',
          lingkungan: '#2c691c',
          hukum: '#850e0d',
        },
        action: {
          primary: '#2653ff',
          magenta: '#fd6eff',
          upload: '#ff0009',
          light: '#7fa4e4',
        },
      },
    },
  },
}
```

Lalu dipakai di Blade seperti `class="bg-pillar-sosial"` atau `class="bg-action-primary"` — konsisten dan gampang di-maintain, daripada hardcode hex di tiap komponen.

## 6. Masih Perlu Verifikasi Manual (tidak bisa diekstrak dari gambar)

- **Nama font** — sampling pixel tidak bisa mendeteksi nama font. Heading terlihat sans-serif bold/extra-bold, mirip Poppins/Inter/Manrope, tapi ini masih tebakan visual. **Cara paling akurat:** buka file Figma aslinya → klik teks heading mana pun → panel kanan "Text" akan menampilkan nama font persis. Tolong cek dan kabari.
- **Border radius pasti** (px) untuk badge pill, kartu, dan input — bisa diestimasi dari gambar tapi nilai pasti sebaiknya dicek langsung di Figma (klik elemen → panel "Corner radius").
- **Spacing/padding pasti** — sama, lebih aman dicek langsung di Figma pakai fitur "Inspect" (klik elemen, lihat panel kanan bawah untuk padding/gap dalam px).

Kalau kamu bisa buka Figma dan screenshot panel "Inspect" untuk beberapa elemen kunci (heading, button, card), aku bisa lengkapi bagian ini jadi 100% akurat juga.
