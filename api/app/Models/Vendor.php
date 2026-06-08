<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $appends = [
        'api_key_masked',
    ];

    protected $fillable = [
        'name',
        'api_key',
        'api_key_previous',
        'api_key_rotated_at',
        'webhook_url',
        'webhook_secret',
        'status',
        'eis_retry_max_attempts',
    ];

    protected $hidden = [
        'api_key',
        'api_key_previous',
        'webhook_secret',
    ];

    protected $casts = [
        'api_key_rotated_at' => 'datetime',
    ];

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function ipWhitelists(): HasMany
    {
        return $this->hasMany(VendorIpWhitelist::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(VendorLicense::class);
    }

    public function billingInvoices(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(BillingInvoice::class, 'billable');
    }

    public function getApiKeyMaskedAttribute(): ?string
    {
        if (empty($this->api_key)) {
            return null;
        }

        return 'vb_****'.substr($this->api_key, -4);
    }

    public function getStatsAttribute(): array
    {
        $merchantCodes = $this->relationLoaded('merchants')
            ? $this->merchants->pluck('merchant_code')
            : $this->merchants()->pluck('merchant_code');

        $todayInvoices = fn () => Invoice::query()
            ->whereIn('merchant_code', $merchantCodes)
            ->whereDate('created_at', today());

        $webhookFailures = $this->relationLoaded('webhookDeliveries')
            ? $this->webhookDeliveries->filter(
                fn (WebhookDelivery $delivery) => ($delivery->status_code ?? 0) >= 400 || $delivery->success === false
            )->count()
            : $this->webhookDeliveries()
                ->where(function ($query) {
                    $query->where('status_code', '>=', 400)
                        ->orWhere('success', false);
                })
                ->count();

        if ($merchantCodes->isEmpty()) {
            return [
                'today_total' => 0,
                'today_ack' => 0,
                'today_rejected' => 0,
                'webhook_failures' => $webhookFailures,
            ];
        }

        return [
            'today_total' => $todayInvoices()->count(),
            'today_ack' => $todayInvoices()->where('eis_status', 'acknowledged')->count(),
            'today_rejected' => $todayInvoices()->where('eis_status', 'rejected')->count(),
            'webhook_failures' => $webhookFailures,
        ];
    }
}
