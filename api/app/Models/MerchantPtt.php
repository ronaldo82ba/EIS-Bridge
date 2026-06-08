<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPtt extends Model
{
    protected $table = 'merchant_ptt';

    protected $fillable = [
        'merchant_id',
        'ptt_number',
        'valid_from',
        'valid_to',
        'status',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
