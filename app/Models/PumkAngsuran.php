<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkAngsuran extends Model
{
    protected $table = 'pumk_angsuran';

    protected $fillable = [
        'pinjaman_id',
        'periode',
        'nomor_bukti',
        'bukti_pembayaran_path',
        'bukti_pembayaran_nama_asli',
        'bukti_pembayaran_mime',
        'bukti_pembayaran_size',
        'bukti_pembayaran_uploaded_at',
        'pokok',
        'bunga',
        'denda',
        'batch_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'pokok' => 'decimal:2',
            'bunga' => 'decimal:2',
            'denda' => 'decimal:2',
            'total' => 'decimal:2',
            'bukti_pembayaran_uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PumkAngsuran $angsuran): void {
            $angsuran->total = (float) ($angsuran->pokok ?? 0)
                + (float) ($angsuran->bunga ?? 0)
                + (float) ($angsuran->denda ?? 0);
        });
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(PumkPinjaman::class, 'pinjaman_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PumkImportBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
