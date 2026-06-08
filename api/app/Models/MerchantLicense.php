<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantLicense extends Model
{
    protected $fillable = [
        'merchant_id',
        'license_plan_id',
        'status',
        'purchased_at',
        'starts_at',
        'ends_at',
        'quantity',
        'metadata',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'quantity' => 'integer',
        'metadata' => 'array',
        'status' => LicenseStatus::class,
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function licensePlan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LicenseStatus::Active->value);
    }

    public function scopeDueForRenewal(Builder $query, int $withinDays = 30): Builder
    {
        return $query
            ->where('status', LicenseStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays($withinDays));
    }
}
