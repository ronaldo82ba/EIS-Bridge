<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'level',
        'message',
        'context',
        'channel',
        'logged_at',
    ];

    protected $casts = [
        'context' => 'array',
        'logged_at' => 'datetime',
    ];
}
