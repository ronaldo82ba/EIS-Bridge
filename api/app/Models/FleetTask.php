<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'command',
        'payload',
        'targets',
        'auth_source',
        'status',
        'total_targets',
        'completed_targets',
        'failed_targets',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'targets' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(FleetTaskResult::class);
    }
}
