<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['related_type', 'related_id', 'from_status', 'to_status', 'changed_by', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
