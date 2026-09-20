<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkBriMitra extends Model
{
    protected $table = 'pumk_bri_mitra';

    protected $fillable = [
        'nama_mitra',
        'alamat',
        'wilayah',
        'sektor_usaha',
        'pinjaman',
        'tenor_raw',
        'tanggal_pencairan',
        'tanggal_jatuh_tempo',
        'source_key',
    ];

    protected function casts(): array
    {
        return [
            'pinjaman' => 'decimal:2',
            'tanggal_pencairan' => 'date',
            'tanggal_jatuh_tempo' => 'date',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PumkBriSnapshotBulanan::class, 'mitra_id');
    }

    public function fasilitas(): HasMany
    {
        return $this->hasMany(PumkBriFasilitas::class, 'mitra_id');
    }
}
