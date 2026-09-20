<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BantuanCsrTarget extends Model
{
    protected $table = 'bantuan_csr_targets';

    protected $fillable = ['bantuan_csr_id', 'target_text', 'order'];

    public function bantuanCsr()
    {
        return $this->belongsTo(BantuanCsr::class);
    }
}
