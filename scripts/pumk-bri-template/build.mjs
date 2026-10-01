import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { SpreadsheetFile, Workbook } from '@oai/artifact-tool';
import JSZip from 'jszip';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const outputPath = path.join(projectRoot, 'resources/templates/pumk-bri-snapshot.xlsx');
const previewDir = path.join(os.tmpdir(), 'tjslinka-pumk-bri-template-preview');
const workbook = Workbook.create();

const instructions = workbook.worksheets.add('Petunjuk');
instructions.showGridLines = false;
instructions.getRange('A:A').format.columnWidth = 25;
instructions.getRange('B:B').format.columnWidth = 105;
instructions.getRange('A1:B17').format.font = { name: 'Arial', size: 11, color: '#172033' };
instructions.getRange('A1:B1').format.fill = '#E8EDF4';
instructions.getRange('A1').values = [['PETUNJUK SNAPSHOT PUMK BRI']];
instructions.getRange('A1').format.font = { name: 'Arial', size: 15, bold: true, color: '#172033' };
instructions.getRange('A1:B1').format.rowHeight = 32;
instructions.getRange('A3:B14').values = [
  ['1. Sheet dan baris', 'Isi sheet Jan mulai baris 3. Ganti nama sheet sesuai bulan: Jan, Feb, Mar, Apr, Mei, Jun, Jul, Ags, Sep, Okt, Nov, Des.'],
  ['2. Tahun', 'Nama bulan tanpa tahun memakai Tahun default pada form upload. Nama seperti Sep 2026 memakai tahun 2026 yang tertulis pada sheet.'],
  ['3. Struktur', 'Pertahankan sembilan header pada baris 2 dan rumus Total Saldo Piutang di H1. Jika perlu beberapa bulan, gandakan sheet; isi semua sheet bulan dan jangan duplikasi bulan/tahun.'],
  ['4. Nominal', 'Isi Pinjaman dan Saldo Piutang sebagai angka, misalnya 1000000. Gunakan format Excel untuk tampilan rupiah; jangan ketik Rp sebagai teks. Saldo piutang tidak boleh negatif dan maksimal dua angka desimal.'],
  ['5. Data wajib', 'Untuk setiap baris mitra, isi Nama Mitra Binaan, Saldo Piutang (termasuk angka 0 bila benar), dan Kolektibilitas. No boleh kosong atau bilangan bulat nol/positif.'],
  ['6. Profil mitra', 'Alamat, Wilayah, Sektor Usaha, Pinjaman, dan Tenor boleh kosong bila tidak tersedia, tetapi isi secara konsisten sesuai sumber untuk pencocokan identitas/fasilitas antarbulan. Jangan menebak data.'],
  ['7. Kolektibilitas', 'Gunakan L = Lancar; KL = Kurang Lancar; D = Diragukan; M = Macet.'],
  ['8. Cakupan', 'Isi daftar lengkap mitra pada bulan tersebut, termasuk saldo nol yang benar-benar berasal dari sumber; jangan hanya perubahan sejak bulan sebelumnya.'],
  ['9. Periksa dan simpan', 'Aktifkan perhitungan otomatis Excel, pastikan H1 sesuai jumlah semua saldo mitra, lalu simpan file melalui Excel setelah selesai mengisi. Sistem membaca hasil rumus yang tersimpan.'],
  ['10. Unggah', 'Pilih Snapshot PUMK BRI, isi Tahun default yang benar, lalu unggah file .xlsx maksimal 10 MB. Hapus sheet bulan yang belum diisi sebelum mengunggah.'],
  ['11. Batasan', 'Template yang masih kosong belum dapat diimpor. Periode yang sudah pernah diimpor tetap memakai data lama. RKA dan realisasi penyaluran dikelola melalui menu RKA & Realisasi BRI.'],
  ['12. Kolom', 'No: urutan; Nama Mitra Binaan: nama sumber; Alamat/Wilayah/Sektor Usaha: profil; Pinjaman: nominal, bukan nomor pinjaman; Tenor: teks sumber; Saldo Piutang: angka; Kolektibilitas: kode kualitas.'],
];
instructions.getRange('A3:A14').format.font = { name: 'Arial', size: 11, bold: true, color: '#172033' };
instructions.getRange('B3:B14').format.wrapText = true;
instructions.getRange('A3:B14').format.rowHeight = 49;
instructions.getRange('A3:B14').format.verticalAlignment = 'center';

const sheet = workbook.worksheets.add('Jan');
sheet.showGridLines = false;
sheet.freezePanes.freezeRows(2);
const widths = { A: 9, B: 29, C: 43, D: 23, E: 24, F: 18, G: 25, H: 21, I: 20 };
for (const [column, width] of Object.entries(widths)) {
  sheet.getRange(`${column}:${column}`).format.columnWidth = width;
}
sheet.getRange('G1').values = [['Total Saldo Piutang']];
sheet.getRange('H1').formulas = [['=SUM(H3:H1048576)']];
sheet.getRange('G1:H1').format = {
  fill: '#E8EDF4',
  font: { name: 'Arial', size: 11, bold: true, color: '#172033' },
  rowHeight: 28,
};
sheet.getRange('H1').setNumberFormat('#,##0.00');
sheet.getRange('A2:I2').values = [[
  'No', 'Nama Mitra Binaan', 'Alamat', 'Wilayah', 'Sektor Usaha',
  'Pinjaman', 'Tenor', 'Saldo Piutang', 'Kolektibilitas',
]];
sheet.getRange('A2:I2').format = {
  fill: '#E8EDF4',
  font: { name: 'Arial', size: 11, bold: true, color: '#172033' },
  rowHeight: 31,
};
sheet.getRange('C3:C500').format.wrapText = true;
sheet.getRange('F3:F500').setNumberFormat('#,##0.00');
sheet.getRange('H3:H500').setNumberFormat('#,##0.00');

workbook.recalculate();
const inspect = await workbook.inspect({
  kind: 'region', sheetId: 'Jan', range: 'G1:I3', maxChars: 1500,
});
console.log(inspect.ndjson);
const preview = await workbook.render({ sheetName: 'Jan', range: 'A1:I6', scale: 1, format: 'png' });
await fs.mkdir(previewDir, { recursive: true });
await fs.writeFile(path.join(previewDir, 'jan.png'), new Uint8Array(await preview.arrayBuffer()));
const previewHelp = await workbook.render({ sheetName: 'Petunjuk', range: 'A1:B14', scale: 0.8, format: 'png' });
await fs.writeFile(path.join(previewDir, 'petunjuk.png'), new Uint8Array(await previewHelp.arrayBuffer()));
await fs.mkdir(path.dirname(outputPath), { recursive: true });
const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);
// The importer reads cached <v> values; Excel must also recalculate after staff edit the sheet.
const archive = await JSZip.loadAsync(await fs.readFile(outputPath));
const workbookEntry = archive.file('xl/workbook.xml');
if (!workbookEntry) throw new Error('xl/workbook.xml is missing from the exported XLSX');
const workbookXml = await workbookEntry.async('string');
if (!workbookXml.endsWith('</x:workbook>')) throw new Error('Unexpected workbook XML structure');
archive.file('xl/workbook.xml', workbookXml.replace('</x:workbook>', '<x:calcPr calcId="0" calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1" /></x:workbook>'));
await fs.writeFile(outputPath, await archive.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE' }));
await fs.rm(`${outputPath}.inspect.ndjson`, { force: true });
console.log(outputPath);
