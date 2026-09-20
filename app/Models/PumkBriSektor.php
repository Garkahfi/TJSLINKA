<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkBriSektor extends Model
{
    protected $table = 'pumk_bri_sektor';

    protected $fillable = ['nama_sektor', 'nilai_portofolio'];

    protected function casts(): array
    {
        return ['nilai_portofolio' => 'decimal:2'];
    }
}
