<?php

namespace App\Services\AdminTjsl;

use App\Models\Program;
use App\Models\StatusLog;
use Illuminate\Http\Request;

final class ProgramWorkflow
{
    public function own(Request $request, Program $program): void
    {
        abort_unless($program->created_by === $request->user()->id, 403);
    }

    public function log(
        Program $program,
        ?string $from,
        string $to,
        int $userId,
        ?string $note = null,
    ): void {
        StatusLog::create([
            'related_type' => 'program',
            'related_id' => $program->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }
}
