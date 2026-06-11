<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetTaskResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'fleet_task_id',
        'fleet_agent_id',
        'agent_id',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(FleetTask::class, 'fleet_task_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(FleetAgent::class, 'fleet_agent_id');
    }
}
