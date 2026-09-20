<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkSektorUsaha extends Model
{
    protected $table = 'pumk_sektor_usaha';

    protected $fillable = ['nama', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function mitra(): HasMany
    {
        return $this->hasMany(PumkMitra::class, 'sektor_usaha_id');
    }
}
