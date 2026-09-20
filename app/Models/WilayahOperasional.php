<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WilayahOperasional extends Model
{
    protected $table = 'wilayah_operasional';

    protected $fillable = [
        'nama',
        'latitude',
        'longitude',
        'realisasi_anggaran',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'realisasi_anggaran' => 'decimal:2',
        ];
    }
}
