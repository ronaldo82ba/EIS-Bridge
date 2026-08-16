<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantDailyVolume extends Model
{
    protected $fillable = [
        'merchant_id',
        'usage_date',
        'invoice_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'invoice_count' => 'integer',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
