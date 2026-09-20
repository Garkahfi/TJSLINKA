<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkBriRingkasan extends Model
{
    protected $table = 'pumk_bri_ringkasan';

    protected $fillable = [
        'tahun',
        'rka_tahun_ini',
        'realisasi_sd_desember',
        'progres_kolaborasi_persen',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'rka_tahun_ini' => 'decimal:2',
            'realisasi_sd_desember' => 'decimal:2',
            'progres_kolaborasi_persen' => 'decimal:2',
        ];
    }
}
