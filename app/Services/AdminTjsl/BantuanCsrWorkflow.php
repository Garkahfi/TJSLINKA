<?php

namespace App\Services\AdminTjsl;

use App\Models\BantuanCsr;
use App\Models\StatusLog;
use Illuminate\Http\Request;

final class BantuanCsrWorkflow
{
    public function log(
        BantuanCsr $item,
        ?string $from,
        string $to,
        int $userId,
        ?string $note = null,
    ): void {
        StatusLog::create([
            'related_type' => 'bantuan_csr',
            'related_id' => $item->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }

    public function own(Request $request, BantuanCsr $item): void
    {
        abort_unless($item->created_by === $request->user()->id, 403);
    }
}
