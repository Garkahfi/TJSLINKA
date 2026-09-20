<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BantuanCsrDetailPhoto extends Model
{
    protected $table = 'bantuan_csr_detail_photos';

    protected $fillable = ['bantuan_csr_detail_id', 'file_path', 'caption'];

    public function detail()
    {
        return $this->belongsTo(BantuanCsrDetail::class, 'bantuan_csr_detail_id');
    }
}
