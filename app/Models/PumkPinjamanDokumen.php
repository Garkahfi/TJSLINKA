<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkPinjamanDokumen extends Model
{
    public const TYPES = [
        'spj_awal' => 'Dokumen SPJ Awal',
        'reschedule_1' => 'Dokumen Reschedule Ke-1',
        'reschedule_2' => 'Dokumen Reschedule Ke-2',
        'reschedule_3' => 'Dokumen Reschedule Ke-3',
        'reschedule_4' => 'Dokumen Reschedule Ke-4',
    ];

    public const CONTRACT_FIELDS = [
        'spj_awal' => 'spj_awal',
        'reschedule_1' => 'reschedule_ke1',
        'reschedule_2' => 'reschedule_ke2',
        'reschedule_3' => 'reschedule_ke3',
        'reschedule_4' => 'reschedule_ke4',
    ];

    protected $table = 'pumk_pinjaman_dokumen';

    protected $fillable = [
        'pinjaman_id', 'jenis_dokumen', 'file_path', 'nama_file_asli',
        'mime_type', 'file_size', 'uploaded_by', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'uploaded_at' => 'datetime'];
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(PumkPinjaman::class, 'pinjaman_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
