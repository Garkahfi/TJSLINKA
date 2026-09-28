<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PumkMonitoringReport extends Model
{
    protected $table = 'pumk_monitoring_reports';

    protected $fillable = [
        'as_of_date', 'source_updated_at', 'source_hash', 'revision',
        'known_loans', 'unknown_loans', 'needs_reconcile', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'source_updated_at' => 'datetime',
            'generated_at' => 'datetime',
            'needs_reconcile' => 'boolean',
        ];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PumkMonitoringPosition::class, 'report_id');
    }
}
