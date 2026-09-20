<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramPhoto extends Model
{
    protected $fillable = ['program_id', 'file_path', 'caption', 'is_cover', 'order'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
