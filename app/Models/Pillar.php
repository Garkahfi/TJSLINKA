<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pillar extends Model
{
    protected $fillable = [
        'name',
        'color_hex',
        'slug',
        'dashboard_rencana_anggaran',
        'dashboard_realisasi_anggaran',
    ];

    protected function casts(): array
    {
        return [
            'dashboard_rencana_anggaran' => 'decimal:2',
            'dashboard_realisasi_anggaran' => 'decimal:2',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function bantuanCsr(): HasMany
    {
        return $this->hasMany(BantuanCsr::class);
    }
}
