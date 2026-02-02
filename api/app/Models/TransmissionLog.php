<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransmissionLog extends Model
{
    protected $fillable = [
        'invoice_id',
        'event',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'metadata'  => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
