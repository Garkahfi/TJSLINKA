<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = ['user_id', 'title', 'message', 'related_type', 'related_id', 'is_read', 'created_at'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
