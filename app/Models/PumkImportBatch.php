<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkImportBatch extends Model
{
    protected $table = 'pumk_import_batches';

    protected $fillable = [
        'nama_file',
        'file_hash',
        'tanggal_acuan',
        'status',
        'total_baris',
        'berhasil',
        'gagal',
        'imported_by',
        'started_at',
        'completed_at',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_acuan' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PumkImportRow::class, 'batch_id');
    }

    public function saldoAwal(): HasMany
    {
        return $this->hasMany(PumkSaldoAwal::class, 'batch_id');
    }

    public function angsuran(): HasMany
    {
        return $this->hasMany(PumkAngsuran::class, 'batch_id');
    }
}
