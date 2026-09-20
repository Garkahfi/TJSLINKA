<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramDocument extends Model
{
    protected $fillable = ['program_id', 'document_type', 'nama_dokumen', 'file_path', 'uploaded_at'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
