<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BantuanCsrPhoto extends Model
{
    protected $fillable = [
        'bantuan_csr_id',
        'file_path',
        'caption',
        'order',
    ];

    public function bantuanCsr(): BelongsTo
    {
        return $this->belongsTo(BantuanCsr::class);
    }
}
