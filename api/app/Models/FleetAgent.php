<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetAgent extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'device_serial',
        'device_model',
        'token_hash',
        'token_encrypted',
        'callback_base_url',
        'status',
        'last_status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_status' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function taskResults(): HasMany
    {
        return $this->hasMany(FleetTaskResult::class);
    }
}
