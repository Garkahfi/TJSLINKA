<?php

namespace App\Services\Pumk;

use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use Illuminate\Http\Request;

class PumkMitraInput
{
    public function validateMitra(Request $request): array
    {
        $contractDocumentMaxMb = (int) ceil((int) config('pumk.contract_document_max_kb') / 1024);

        return $request->validate([
            'nama_mitra' => ['required', 'string', 'max:255'],
            'jenis_usaha' => ['nullable', 'string', 'max:255'],
            'sektor_usaha_id' => ['nullable', 'integer', 'exists:pumk_sektor_usaha,id'],
            'wilayah_id' => ['nullable', 'integer', 'exists:pumk_wilayah,id'],
            'alamat' => ['nullable', 'string', 'max:3000'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'no_ktp' => ['nullable', 'string', 'max:64'],
            'no_telepon' => ['nullable', 'string', 'max:64'],
            'no_rekening' => ['nullable', 'string', 'max:64'],
            'spj_awal' => ['nullable', 'string', 'max:255'],
            'reschedule_ke1' => ['nullable', 'string', 'max:255'],
            'reschedule_ke2' => ['nullable', 'string', 'max:255'],
            'reschedule_ke3' => ['nullable', 'string', 'max:255'],
            'reschedule_ke4' => ['nullable', 'string', 'max:255'],
            'jenis_jaminan' => ['nullable', 'string', 'max:3000'],
            'jaminan_no_pol' => ['nullable', 'string', 'max:255'],
            'jaminan_no_bpkb' => ['nullable', 'string', 'max:255'],
            'jaminan_merk' => ['nullable', 'string', 'max:255'],
            'jaminan_type' => ['nullable', 'string', 'max:255'],
            'jaminan_tahun_kendaraan' => ['nullable', 'string', 'max:255'],
            'jaminan_no_sertifikat' => ['nullable', 'string', 'max:255'],
            'jaminan_luas' => ['nullable', 'string', 'max:255'],
            'jaminan_atas_nama' => ['nullable', 'string', 'max:255'],
            'jaminan_alamat' => ['nullable', 'string', 'max:3000'],
            'tanggal_pencairan' => ['nullable', 'date'],
            'mulai_angsuran' => ['nullable', 'date'],
            'selesai_angsuran' => ['nullable', 'date', 'after_or_equal:mulai_angsuran'],
            'pinjaman_pokok' => ['nullable', 'numeric', 'min:0'],
            'persen_bunga' => ['nullable', 'numeric', 'min:0'],
            'pinjaman_bunga' => ['nullable', 'numeric', 'min:0'],
            'nilai_angsuran_bulanan' => ['nullable', 'numeric', 'min:0'],
            'dokumen_spj_awal' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_1' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_2' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_3' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_4' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
        ], [
            'dokumen_spj_awal.max' => "Ukuran Dokumen SPJ Awal maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_1.max' => "Ukuran Dokumen Reschedule Ke-1 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_2.max' => "Ukuran Dokumen Reschedule Ke-2 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_3.max' => "Ukuran Dokumen Reschedule Ke-3 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_4.max' => "Ukuran Dokumen Reschedule Ke-4 maksimal {$contractDocumentMaxMb} MB.",
        ]);
    }

    public function mitraPayload(array $data): array
    {
        $sektor = filled($data['sektor_usaha_id'] ?? null)
            ? PumkSektorUsaha::find($data['sektor_usaha_id'])
            : null;
        $wilayah = filled($data['wilayah_id'] ?? null)
            ? PumkWilayah::find($data['wilayah_id'])
            : null;

        return [
            'nama_mitra' => trim($data['nama_mitra']),
            'jenis_usaha' => $data['jenis_usaha'] ?? null,
            'sektor_usaha_id' => $sektor?->id,
            'sektor_sumber' => $sektor?->nama,
            'wilayah_id' => $wilayah?->id,
            'wilayah_sumber' => $wilayah?->nama,
            'alamat' => $data['alamat'] ?? null,
            'nama_pemilik' => $data['nama_pemilik'] ?? null,
            'no_ktp_encrypted' => $data['no_ktp'] ?? null,
            'no_telepon_encrypted' => $data['no_telepon'] ?? null,
            'no_rekening_encrypted' => $data['no_rekening'] ?? null,
        ];
    }

    public function pinjamanPayload(array $data): array
    {
        return [
            'spj_awal' => $data['spj_awal'] ?? null,
            'reschedule_ke1' => $data['reschedule_ke1'] ?? null,
            'reschedule_ke2' => $data['reschedule_ke2'] ?? null,
            'reschedule_ke3' => $data['reschedule_ke3'] ?? null,
            'reschedule_ke4' => $data['reschedule_ke4'] ?? null,
            'jenis_jaminan' => $data['jenis_jaminan'] ?? null,
            'jaminan_no_pol' => $data['jaminan_no_pol'] ?? null,
            'jaminan_no_bpkb' => $data['jaminan_no_bpkb'] ?? null,
            'jaminan_merk' => $data['jaminan_merk'] ?? null,
            'jaminan_type' => $data['jaminan_type'] ?? null,
            'jaminan_tahun_kendaraan' => $data['jaminan_tahun_kendaraan'] ?? null,
            'jaminan_no_sertifikat' => $data['jaminan_no_sertifikat'] ?? null,
            'jaminan_luas' => $data['jaminan_luas'] ?? null,
            'jaminan_atas_nama' => $data['jaminan_atas_nama'] ?? null,
            'jaminan_alamat' => $data['jaminan_alamat'] ?? null,
            'tanggal_pencairan' => $data['tanggal_pencairan'] ?? null,
            'mulai_angsuran' => $data['mulai_angsuran'] ?? null,
            'selesai_angsuran' => $data['selesai_angsuran'] ?? null,
            'pinjaman_pokok' => $data['pinjaman_pokok'] ?? null,
            'persen_bunga' => $data['persen_bunga'] ?? null,
            'pinjaman_bunga' => $data['pinjaman_bunga'] ?? null,
            'nilai_angsuran_bulanan' => $data['nilai_angsuran_bulanan'] ?? null,
        ];
    }
}
