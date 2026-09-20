<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TpbDashboard extends Model
{
    protected $table = 'tpb_dashboard';

    protected $fillable = [
        'nomor_tpb',
        'nama_tpb',
        'rencana_anggaran',
        'realisasi_anggaran',
    ];

    protected function casts(): array
    {
        return [
            'rencana_anggaran' => 'decimal:2',
            'realisasi_anggaran' => 'decimal:2',
        ];
    }
}
