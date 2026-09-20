<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BantuanCsr extends Model
{
    protected $table = 'bantuan_csr';

    public const PHASE_ONE_DOCUMENTS = [
        'A' => 'Proposal Pengajuan Program',
        'B' => 'Kelengkapan Survei',
    ];

    public const PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE = 'bast_tambahan';

    public const REVIEW_STATUSES = [
        'pending_fase1',
        'pending_fase2',
    ];

    public const OVERVIEW_STATUSES = [
        'approved_fase1',
        'pending_fase2',
        'completed',
    ];

    protected $fillable = [
        'nama_program_bantuan',
        'deskripsi_bantuan',
        'pillar_id',
        'rencana_anggaran',
        'realisasi_anggaran',
        'status',
        'is_archived',
        'created_by',
        'submitted_at',
        'fase1_reviewed_by',
        'fase1_reviewed_at',
        'fase1_rejected_reason',
        'fase2_reviewed_by',
        'fase2_reviewed_at',
        'fase2_rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'submitted_at' => 'datetime',
            'fase1_reviewed_at' => 'datetime',
            'fase2_reviewed_at' => 'datetime',
            'rencana_anggaran' => 'decimal:2',
            'realisasi_anggaran' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    public function fase1Reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fase1_reviewed_by');
    }

    public function fase2Reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fase2_reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BantuanCsrDocument::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(BantuanCsrTarget::class)->orderBy('order');
    }

    public function details(): HasMany
    {
        return $this->hasMany(BantuanCsrDetail::class)->orderBy('order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BantuanCsrPhoto::class)->orderBy('order');
    }

    public function canBeEdited(): bool
    {
        // Penolakan dokumen awal adalah keputusan final. Hanya draft yang
        // dapat diubah; penolakan BAST mengembalikan status ke
        // approved_fase1 sehingga Admin hanya dapat memperbaiki BAST.
        return $this->status === 'draft' && ! $this->is_archived;
    }

    public function canUploadBast(): bool
    {
        return $this->status === 'approved_fase1' && ! $this->is_archived;
    }
}
