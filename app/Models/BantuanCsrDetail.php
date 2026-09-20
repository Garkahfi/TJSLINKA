<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BantuanCsrDetail extends Model
{
    protected $table = 'bantuan_csr_details';

    protected $fillable = ['bantuan_csr_id', 'rincian_kegiatan', 'penerima_bantuan', 'jenis_bantuan', 'quality', 'nominal_bantuan', 'order'];

    public function bantuanCsr()
    {
        return $this->belongsTo(BantuanCsr::class);
    }

    public function photos()
    {
        return $this->hasMany(BantuanCsrDetailPhoto::class);
    }
}
