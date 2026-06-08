<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Merchant extends Model
{
    protected $fillable = [
        'vendor_id',
        'merchant_code',
        'name',
        'tin',
        'address',
        'status',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'merchant_code', 'merchant_code');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(MerchantCertificate::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(MerchantCertificate::class)->latestOfMany();
    }

    public function ptt(): HasOne
    {
        return $this->hasOne(MerchantPtt::class);
    }

    public function getStatsAttribute(): array
    {
        $todayInvoices = fn () => Invoice::query()
            ->where('merchant_code', $this->merchant_code)
            ->whereDate('created_at', today());

        return [
            'today_total' => $todayInvoices()->count(),
            'today_ack' => $todayInvoices()->where('eis_status', 'acknowledged')->count(),
            'today_rejected' => $todayInvoices()->where('eis_status', 'rejected')->count(),
            'failures' => Invoice::query()
                ->where('merchant_code', $this->merchant_code)
                ->where('processing_status', 'retry_failed')
                ->count(),
        ];
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(MerchantLicense::class);
    }

    public function billingInvoices(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(BillingInvoice::class, 'billable');
    }
}
