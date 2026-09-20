<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkImportRow extends Model
{
    protected $table = 'pumk_import_rows';

    protected $fillable = [
        'batch_id',
        'source_row_number',
        'row_hash',
        'status',
        'error_message',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PumkImportBatch::class, 'batch_id');
    }
}
