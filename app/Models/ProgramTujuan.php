<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramTujuan extends Model
{
    protected $table = 'program_tujuan';

    protected $fillable = [
        'foto_path',
        'deskripsi',
        'urutan',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
