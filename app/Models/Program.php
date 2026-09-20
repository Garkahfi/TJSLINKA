<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    public const COOPERATION_TYPES = [
        'pks',
        'non_pks',
    ];

    public const PHASE_ONE_DOCUMENTS = [
        'proposal_pengajuan_program' => 'Proposal Pengajuan Program',
        'kelengkapan_survei' => 'Kelengkapan Survei',
        'kajian_kelayakan' => 'Kajian Kelayakan Kerja Sama',
        'kajian_mitigasi_risiko' => 'Kajian Risiko',
        'perjanjian_kerja_sama' => 'Perjanjian Kerja Sama',
    ];

    public const PUBLIC_STATUSES = [
        'pending_fase1',
        'approved_fase1',
        'pending_fase2',
        'completed',
    ];

    /**
     * Program yang sudah memperoleh persetujuan awal Super Admin dan layak
     * ditampilkan pada Overview Program TJSL.
     */
    public const OVERVIEW_STATUSES = [
        'approved_fase1',
        'pending_fase2',
        'completed',
    ];

    protected $fillable = [
        'slug',
        'pillar_id',
        'jenis_kerjasama',
        'nama_program',
        'deskripsi_program',
        'sasaran_program',
        'lokasi_program',
        'mitra_program',
        'rencana_anggaran',
        'realisasi_anggaran',
        'tujuan_program',
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

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $this->hasMany(ProgramDocument::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProgramPhoto::class)->orderBy('order');
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(ProgramTujuan::class)->orderBy('urutan');
    }

    public function bantuanCsr(): HasMany
    {
        return $this->hasMany(BantuanCsr::class);
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'draft' && ! $this->is_archived;
    }

    public function canUploadBast(): bool
    {
        return $this->status === 'approved_fase1' && ! $this->is_archived;
    }

    /**
     * Dokumen awal yang wajib untuk jenis kerja sama Program Internal ini.
     *
     * Program lama yang belum memiliki nilai jenis_kerjasama tetap dianggap PKS
     * agar alur dan kelengkapan dokumennya tidak berubah.
     */
    public function phaseOneDocuments(): array
    {
        if ($this->jenis_kerjasama !== 'non_pks') {
            return self::PHASE_ONE_DOCUMENTS;
        }

        return array_intersect_key(
            self::PHASE_ONE_DOCUMENTS,
            array_flip([
                'proposal_pengajuan_program',
                'kelengkapan_survei',
            ]),
        );
    }

    public function cooperationLabel(): string
    {
        return $this->jenis_kerjasama === 'non_pks' ? 'NON-PKS' : 'PKS';
    }
}
