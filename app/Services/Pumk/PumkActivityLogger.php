<?php

namespace App\Services\Pumk;

use App\Models\PumkActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PumkActivityLogger
{
    /** @param array<string, int|string|float|bool|null> $metadata */
    public function record(
        string $action,
        string $module,
        string $description,
        ?Model $entity = null,
        ?User $actor = null,
        array $metadata = [],
    ): PumkActivityLog {
        $actor ??= auth('pumk')->user();

        return PumkActivityLog::create([
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role ?? 'system',
            'action' => $action,
            'module' => $module,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description_safe' => $description,
            'metadata_safe' => $metadata === [] ? null : $metadata,
        ]);
    }
}
