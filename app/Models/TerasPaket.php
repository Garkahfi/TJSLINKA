<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerasPaket extends Model
{
    protected $table = 'teras_paket';

    protected $fillable = [
        'nama_paket',
        'foto_path',
        'harga',
        'tipe_harga',
        'isi_paket',
        'catatan_khusus',
        'urutan',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'isi_paket' => 'array',
            'is_active' => 'boolean',
        ];
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
        // aktif tanpa bergantung pada APP_URL.
        return '/storage/'.ltrim($this->foto_path, '/');
    }

    public function getFormattedHargaAttribute(): string
    {
        $prefix = $this->tipe_harga === 'maksimal' ? 'Maks Rp ' : 'Rp ';

        return $prefix.number_format((float) $this->harga, 0, ',', '.');
    }
}
