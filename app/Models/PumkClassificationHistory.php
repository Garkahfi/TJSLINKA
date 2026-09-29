<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkClassificationHistory extends Model
{
    protected $table = 'pumk_classification_history';

    protected $fillable = [
        'mitra_id', 'pinjaman_id', 'attribute', 'value', 'effective_from',
        'recorded_at', 'source_kind', 'source_ref', 'recorded_by', 'fingerprint',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'recorded_at' => 'datetime'];
    }
}
