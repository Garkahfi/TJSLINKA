<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkBriKualitas extends Model
{
    protected $table = 'pumk_bri_kualitas';

    protected $fillable = ['kategori', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'decimal:2'];
    }
}
