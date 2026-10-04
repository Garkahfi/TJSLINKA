<?php

namespace App\Services\Pumk;

use Illuminate\Http\Request;

class PumkInstallmentInput
{
    public function validateAngsuran(Request $request): array
    {
        $request->merge([
            'pokok' => $this->normalizeRupiah($request->input('pokok')),
            'bunga' => $this->normalizeRupiah($request->input('bunga')),
            'denda' => $this->normalizeRupiah($request->input('denda'), true),
        ]);

        return $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
            'nomor_bukti' => ['nullable', 'string', 'max:255'],
            'pokok' => ['required', 'numeric', 'min:0'],
            'bunga' => ['required', 'numeric', 'min:0'],
            'denda' => ['required', 'numeric', 'min:0'],
            'bukti_pembayaran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.config('pumk.payment_proof_max_kb')],
            'hapus_saldo_awal' => ['nullable', 'boolean'],
        ], [
            'pokok.numeric' => 'Pokok harus berupa nominal Rupiah.',
            'bunga.numeric' => 'Bunga harus berupa nominal Rupiah atau tanda - jika tidak ada.',
            'denda.numeric' => 'Denda harus berupa nominal Rupiah atau tanda - jika tidak ada.',
        ]);
    }

    private function normalizeRupiah(mixed $value, bool $emptyAsZero = false): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '-' || ($emptyAsZero && $value === '')) {
            return '0';
        }

        if ($value === '') {
            return $value;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits === '' ? $value : $digits;
    }
}
