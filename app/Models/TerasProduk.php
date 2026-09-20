<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerasProduk extends Model
{
    protected $table = 'teras_produk';

    protected $fillable = [
        'nama_produk',
        'nama_umkm',
        'foto_path',
        'deskripsi',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->foto_path) {
            return null;
        }

        if (str_starts_with($this->foto_path, 'images/') || str_starts_with($this->foto_path, '/')) {
            return asset(ltrim($this->foto_path, '/'));
        }

        // Path same-origin membuat browser memakai host dan port halaman
        // aktif. Ini tidak bergantung pada APP_URL yang dapat berbeda antara
        // localhost, 127.0.0.1:8000, dan domain produksi.
        return '/storage/'.ltrim($this->foto_path, '/');
    }
}
