<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    public static function generateBridgeTransactionId(): string
    {
        return 'EB-'.(string) Str::ulid();
    }

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

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_code', 'merchant_code');
    }

    public function resolveMerchant(): ?Merchant
    {
        return $this->merchant ?: Merchant::query()
            ->where('merchant_code', $this->merchant_code)
            ->first();
    }

    public function resolveVendor(): ?Vendor
    {
        return $this->resolveMerchant()?->vendor;
    }

    public function scopeForVendor(Builder $query, Vendor|int $vendor): Builder
    {
        $vendorId = $vendor instanceof Vendor ? $vendor->id : $vendor;

        return $query->whereIn('merchant_code', Merchant::query()
            ->select('merchant_code')
            ->where('vendor_id', $vendorId));
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function transmissionLogs(): HasMany
    {
        return $this->hasMany(TransmissionLog::class);
    }

    /** @alias transmissionLogs() */
    public function logs(): HasMany
    {
        return $this->transmissionLogs();
    }
}
