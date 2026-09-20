# Referensi Geografis Peta PUMK BRI

Fitur choropleth **Sebaran Penyaluran Dana PUMK (BRI)** memakai salinan lokal:

```text
public/geo/indonesia-kabupaten-kota.geojson
```

## Sumber

- Dataset: `GeoJson-Indonesia-38-Provinsi/Kabupaten/38 Provinsi Indonesia - Kabupaten.json`
- Repositori: `https://github.com/ardian28/GeoJson-Indonesia-38-Provinsi`
- Sumber geometri yang dinyatakan oleh repositori: Badan Informasi Geospasial,
  `geoservice.big.go.id`
- Lisensi repositori: MIT
- SHA-256 salinan lokal: `EBF19CE23C0B5894E7F29E99BE39F5AAADB50EF6C405098C3CF17887D2B87F9E`
- Diambil: 18 September 2026

GeoJSON hanya menjadi referensi polygon wilayah administratif. Data jumlah
Mitra, fasilitas, outstanding, dan kolektibilitas tetap berasal dari
`pumk_bri_snapshot_bulanan` pada snapshot bulan terakhir tahun terpilih.

Tidak ada latitude/longitude sintetis yang dibuat atau disimpan ke snapshot.
Wilayah sumber yang tidak cocok dengan properti `WADMKK` tetap masuk agregasi
dashboard dan ditandai sebagai wilayah yang belum dipetakan.
