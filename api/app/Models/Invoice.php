<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'bridge_transaction_id',
        'transaction_id',
        'merchant_code',
        'branch_code',
        'pos_device_id',
        'raw_pos_json',
        'bir_json',
        'signed_json',
        'processing_status',
        'eis_status',
        'eis_reference_no',
    ];

    protected $casts = [
        'raw_pos_json' => 'array',
        'bir_json'     => 'array',
        'signed_json'  => 'array',
    ];

    public function transmissionLogs(): HasMany
    {
        return $this->hasMany(TransmissionLog::class);
    }
}
