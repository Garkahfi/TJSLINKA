<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkMitra extends Model
{
    protected $table = 'pumk_mitra';

    protected $fillable = [
        'nama_mitra',
        'jenis_usaha',
        'sektor_usaha_id',
        'sektor_sumber',
        'wilayah_id',
        'wilayah_sumber',
        'alamat',
        'nama_pemilik',
        'no_ktp_encrypted',
        'no_telepon_encrypted',
        'no_rekening_encrypted',
        'source_key',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'no_ktp_encrypted' => 'encrypted',
            'no_telepon_encrypted' => 'encrypted',
            'no_rekening_encrypted' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PumkMitra $mitra): void {
            $ktp = $mitra->no_ktp_encrypted;
            $mitra->no_ktp_hash = filled($ktp)
                ? hash_hmac('sha256', trim((string) $ktp), (string) config('app.key'))
                : null;
        });
    }

    public function sektorUsaha(): BelongsTo
    {
        return $this->belongsTo(PumkSektorUsaha::class, 'sektor_usaha_id');
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(PumkWilayah::class, 'wilayah_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pinjaman(): HasMany
    {
        return $this->hasMany(PumkPinjaman::class, 'mitra_id');
    }

    public function pinjamanAktif(): HasMany
    {
        return $this->pinjaman()->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true);
    }
}
