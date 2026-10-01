<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumkLoanClosure extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'reopened_at' => 'datetime', 'settlement_snapshot' => 'array'];
    }
}
