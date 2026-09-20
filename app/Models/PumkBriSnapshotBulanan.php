<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkBriSnapshotBulanan extends Model
{
    protected $table = 'pumk_bri_snapshot_bulanan';

    protected $fillable = [
        'mitra_id',
        'fasilitas_id',
        'bulan',
        'tahun',
        'saldo_piutang',
        'kolektibilitas_kode',
        'kolektibilitas_label',
        'source_sheet',
        'source_row',
        'no_urut_sumber',
        'nama_mitra_sumber',
        'alamat_sumber',
        'wilayah_sumber',
        'sektor_usaha_sumber',
        'pinjaman_sumber',
        'tenor_sumber',
        'tanggal_pencairan_sumber',
        'tanggal_jatuh_tempo_sumber',
        'profil_sumber_terverifikasi',
        'source_payload',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'saldo_piutang' => 'decimal:2',
            'source_row' => 'integer',
            'no_urut_sumber' => 'integer',
            'pinjaman_sumber' => 'decimal:2',
            'tanggal_pencairan_sumber' => 'date',
            'tanggal_jatuh_tempo_sumber' => 'date',
            'profil_sumber_terverifikasi' => 'boolean',
            'source_payload' => 'array',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(PumkBriMitra::class, 'mitra_id');
    }

    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(PumkBriFasilitas::class, 'fasilitas_id');
    }
}
