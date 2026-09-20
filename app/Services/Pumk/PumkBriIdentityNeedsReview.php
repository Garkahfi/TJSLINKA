<?php

namespace App\Services\Pumk;

use RuntimeException;

class PumkBriIdentityNeedsReview extends RuntimeException
{
    /** @param list<array<string, mixed>> $cases */
    public function __construct(public readonly array $cases)
    {
        parent::__construct(count($cases).' baris memiliki identitas ambigu; periode tidak diubah.');
    }
}
