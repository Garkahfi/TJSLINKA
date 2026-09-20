<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkBriPenyaluranBulanan extends Model
{
    protected $table = 'pumk_bri_penyaluran_bulanan';

    protected $fillable = ['tahun', 'bulan', 'nominal_penyaluran', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'nominal_penyaluran' => 'decimal:2',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
