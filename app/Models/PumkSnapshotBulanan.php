<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkSnapshotBulanan extends Model
{
    protected $table = 'pumk_snapshot_bulanan';

    protected $fillable = ['bulan', 'tahun', 'kategori', 'tipe', 'nilai'];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'nilai' => 'decimal:2',
        ];
    }
}
