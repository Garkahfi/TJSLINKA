<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PumkActivityLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'actor_role',
        'action',
        'module',
        'entity_type',
        'entity_id',
        'description_safe',
        'metadata_safe',
    ];

    protected function casts(): array
    {
        return ['metadata_safe' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
