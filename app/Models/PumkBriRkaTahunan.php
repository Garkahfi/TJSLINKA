<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkBriRkaTahunan extends Model
{
    protected $table = 'pumk_bri_rka_tahunan';

    protected $fillable = ['tahun', 'nominal_rka', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'nominal_rka' => 'decimal:2',
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
