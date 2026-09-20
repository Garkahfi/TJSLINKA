<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkBriSaldoBulanan extends Model
{
    protected $table = 'pumk_bri_saldo_bulanan';

    protected $fillable = ['sektor_atau_kategori', 'tipe', 'bulan', 'tahun', 'nilai'];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'nilai' => 'decimal:2',
        ];
    }
}
