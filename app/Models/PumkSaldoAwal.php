<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkSaldoAwal extends Model
{
    protected $table = 'pumk_saldo_awal';

    protected $fillable = [
        'pinjaman_id',
        'cutoff_date',
        'pokok_masuk',
        'bunga_masuk',
        'denda',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'pokok_masuk' => 'decimal:2',
            'bunga_masuk' => 'decimal:2',
            'denda' => 'decimal:2',
        ];
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(PumkPinjaman::class, 'pinjaman_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PumkImportBatch::class, 'batch_id');
    }
}
