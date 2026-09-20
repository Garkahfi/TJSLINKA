<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkBriFasilitas extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_LUNAS = 'lunas';

    protected $table = 'pumk_bri_fasilitas';

    protected $fillable = [
        'mitra_id', 'reference_key', 'pinjaman', 'tenor_raw', 'tanggal_pencairan',
        'sektor_usaha', 'status', 'first_seen_period', 'last_seen_period',
    ];

    protected function casts(): array
    {
        return [
            'pinjaman' => 'decimal:2',
            'tanggal_pencairan' => 'date',
            'first_seen_period' => 'date',
            'last_seen_period' => 'date',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(PumkBriMitra::class, 'mitra_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PumkBriSnapshotBulanan::class, 'fasilitas_id');
    }
}
