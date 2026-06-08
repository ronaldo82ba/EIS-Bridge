<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'vendor_id',
        'invoice_id',
        'event',
        'request_url',
        'attempt',
        'status_code',
        'response_body',
        'success',
        'delivered_at',
    ];

    protected $casts = [
        'success'      => 'boolean',
        'delivered_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
