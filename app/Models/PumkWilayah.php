<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkWilayah extends Model
{
    protected $table = 'pumk_wilayah';

    protected $fillable = ['nama', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function mitra(): HasMany
    {
        return $this->hasMany(PumkMitra::class, 'wilayah_id');
    }
}
