<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkBriIdentityReview extends Model
{
    protected $table = 'pumk_bri_identity_reviews';

    protected $fillable = [
        'tahun', 'bulan', 'source_sheet', 'source_row', 'source_profile',
        'candidate_fasilitas_ids', 'reason', 'status', 'resolved_fasilitas_id',
        'source_fingerprint', 'resolution_action',
        'resolved_mitra_id',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'source_row' => 'integer',
            'source_profile' => 'array',
            'candidate_fasilitas_ids' => 'array',
        ];
    }
}
