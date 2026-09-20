<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BantuanCsrDocument extends Model
{
    protected $table = 'bantuan_csr_documents';

    protected $fillable = ['bantuan_csr_id', 'document_type', 'nama_dokumen', 'file_path', 'uploaded_at'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function bantuanCsr()
    {
        return $this->belongsTo(BantuanCsr::class);
    }
}
