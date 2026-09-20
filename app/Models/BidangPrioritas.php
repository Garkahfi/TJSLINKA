<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidangPrioritas extends Model
{
    protected $table = 'bidang_prioritas';

    protected $fillable = [
        'nama_bidang',
        'rencana_anggaran',
        'realisasi_anggaran',
        'penyerapan_persen',
    ];

    protected function casts(): array
    {
        return [
            'rencana_anggaran' => 'decimal:2',
            'realisasi_anggaran' => 'decimal:2',
            'penyerapan_persen' => 'decimal:2',
        ];
    }
}
